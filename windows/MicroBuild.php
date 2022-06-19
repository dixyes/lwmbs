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

class CliBuild
{
    const CLI_TARGET = [
        '$(BUILD_DIR)\php.exe: $(DEPS_CLI) $(PHP_GLOBAL_OBJS) $(CLI_GLOBAL_OBJS) $(STATIC_EXT_OBJS) $(ASM_OBJS) $(BUILD_DIR)\php.exe.res $(BUILD_DIR)\php.exe.manifest',
        '"$(LINK)" /nologo $(PHP_GLOBAL_OBJS_RESP) $(CLI_GLOBAL_OBJS_RESP) $(STATIC_EXT_OBJS_RESP) $(STATIC_EXT_LIBS) $(LIBS) $(LIBS_CLI) $(BUILD_DIR)\php.exe.res /out:$(BUILD_DIR)\php.exe $(LDFLAGS) $(LDFLAGS_CLI)',
        '-@$(_VC_MANIFEST_EMBED_EXE)',
    ];

    public function __construct(
        private Config $config
    ) {
    }

    public function build(bool $allStatic = false): void
    {
        Log::i("building cli");

        $crt = match ($this->config->vsVer) {
            '14' => 'vc14',
            '15' => 'vc15',
            '16' => 'vs16',
            '17' => 'vs17',
        };
        $env = "\"{$this->config->phpBinarySDKDir}\\phpsdk-starter.bat\" -c {$crt} -a {$this->config->arch} ";

        $ret = 0;
        passthru(
            "cd src\php-src && $env -t buildconf.bat",
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to buildconf for cli");
        }

        passthru(
            "cd src\php-src && $env" .
                '-t configure.bat ' .
                '--task-args "' .
                '--with-prefix=C:\php ' .
                '--with-php-build=..\deps ' .
                '--disable-debug ' .
                '--enable-debug-pack ' .
                '--disable-all ' .
                '--disable-cgi ' .
                '--disable-phpdbg ' .
                '--enable-cli ' .
                ($this->config->zts ? '--enable-zts' : '') . ' ' .
                Extension::makeExtensionArgs($this->config) . '"',
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to configure cli");
        }

        // workaround for bInterlockedExchange8 missing (seems to be a MSVC bug)
        $zend_atomic = file_get_contents('src\php-src\Zend\zend_atomic.h');
        $zend_atomic = preg_replace('/\bInterlockedExchange8\b/', '_InterlockedExchange8', $zend_atomic);
        file_put_contents('src\php-src\Zend\zend_atomic.h', $zend_atomic);

        // workaround for static cli build (needs cli_static.patch from micro also)
        $makefile = file_get_contents('src\php-src\Makefile');
        $makefile = preg_replace('/\$\(BUILD_DIR\)\\\php\.exe:\s[^\r\n]+/m', implode("\r\n\t", self::CLI_TARGET) . "\r\n\r\nnotused:", $makefile);
        file_put_contents('src\php-src\Makefile', $makefile);

        file_put_contents('src\php-src\nmake_wrapper.bat', 'nmake %*');

        passthru(
            "cd src\php-src && $env" .
                '-t nmake_wrapper.bat ' .
                '--task-args "/nologo clean"',
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to make clean for cli");
        }

        passthru(
            "cd src\php-src && $env" .
                '-t nmake_wrapper.bat ' . 
                '--task-args "/nologo php.exe"',
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to make cli");
        }
    }
}
