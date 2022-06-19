<?php

/**
 * Copyright (c) 2022 Yun Dou <dixyes@gmail.com>
 *
 * lwmbs is licensed under Mulan PSL v2. You can use this
 * software according to the terms and conditions of the
 * Mulan PSL v2. You may obtain a copy of Mulan PSL v2 at:
 *
 * http://license.coscl.org.cn/MulanPSL2
 *
 * THIS SOFTWARE IS PROVIDED ON AN "AS IS" BASIS,
 * WITHOUT WARRANTIES OF ANY KIND, EITHER EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO NON-INFRINGEMENT,
 * MERCHANTABILITY OR FIT FOR A PARTICULAR PURPOSE.
 *
 * See the Mulan PSL v2 for more details.
 */

declare(strict_types=1);

class MicroBuild
{

    public function __construct(
        private Config $config
    ) {
    }

    public function build(bool $allStatic = false): void
    {
        Log::i("building micro");

        Util::patchConfigW32();

        $ret = 0;
        passthru(
            "cd src\php-src && {$this->config->phpBinarySDKCmd} -t buildconf.bat",
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to buildconf for micro");
        }

        passthru(
            "cd src\php-src && {$this->config->phpBinarySDKCmd} " .
                '-t configure.bat ' .
                '--task-args "' .
                '--with-prefix=C:\php ' .
                '--with-php-build=..\..\deps ' .
                '--disable-debug ' .
                '--enable-debug-pack ' .
                '--disable-all ' .
                '--disable-cgi ' .
                '--disable-phpdbg ' .
                '--disable-cli ' .
                '--enable-micro ' .
                ($this->config->zts ? '--enable-zts' : '') . ' ' .
                Extension::makeExtensionArgs($this->config) . '"',
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to configure micro");
        }

        if ($this->config->arch === 'arm64') {
            // workaround for InterlockedExchange8 missing (seems to be a MSVC bug)
            $zend_atomic = file_get_contents('src\php-src\Zend\zend_atomic.h');
            $zend_atomic = preg_replace('/\bInterlockedExchange8\b/', '_InterlockedExchange8', $zend_atomic);
            file_put_contents('src\php-src\Zend\zend_atomic.h', $zend_atomic);
        }

        // add indirect libs
        $extra_libs = '';
        if ($this->config->getLib('zstd')) {
            $extra_libs .= ' zstd.lib';
        }
        if ($this->config->getLib('brotli')) {
            $extra_libs .= ' brotlidec-static.lib brotlicommon-static.lib';
        }

        file_put_contents('src\php-src\nmake_wrapper.bat', 'nmake /nologo LIBS_MICRO="' . $extra_libs . ' ws2_32.lib shell32.lib" %*');

        passthru(
            "cd src\php-src && {$this->config->phpBinarySDKCmd} " .
                '-t nmake_wrapper.bat ' . 
                '--task-args micro',
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to make micro");
        }
    }
}
