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
        '$(BUILD_DIR)\php.exe: generated_files $(DEPS_CLI) $(PHP_GLOBAL_OBJS) $(CLI_GLOBAL_OBJS) $(STATIC_EXT_OBJS) $(ASM_OBJS) $(BUILD_DIR)\php.exe.res $(BUILD_DIR)\php.exe.manifest',
        '"$(LINK)" /nologo $(PHP_GLOBAL_OBJS_RESP) $(CLI_GLOBAL_OBJS_RESP) $(STATIC_EXT_OBJS_RESP) $(STATIC_EXT_LIBS) $(LIBS) $(LIBS_CLI) $(BUILD_DIR)\php.exe.res /out:$(BUILD_DIR)\php.exe $(LDFLAGS) $(LDFLAGS_CLI) /ltcg /nodefaultlib:msvcrt /nodefaultlib:msvcrtd /ignore:4286',
        '-@$(_VC_MANIFEST_EMBED_EXE)',
    ];

    public function __construct(
        private Config $config
    ) {
    }

    public function build(bool $allStatic = false): void
    {
        Log::i("building cli");

        $ret = 0;
        passthru(
            "cd src\php-src && {$this->config->phpBinarySDKCmd} -t buildconf.bat",
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to buildconf for cli");
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
                '--enable-cli ' .
                ($this->config->zts ? '--enable-zts' : '') . ' ' .
                Extension::makeExtensionArgs($this->config) . '"',
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to configure cli");
        }

        if ($this->config->arch === 'arm64') {
            // workaround for InterlockedExchange8 missing (seems to be a MSVC bug)
            $zend_atomic = file_get_contents('src\php-src\Zend\zend_atomic.h');
            $zend_atomic = preg_replace('/\bInterlockedExchange8\b/', '_InterlockedExchange8', $zend_atomic);
            file_put_contents('src\php-src\Zend\zend_atomic.h', $zend_atomic);
        }

        // workaround for static cli build (needs cli_static.patch from micro also)
        $makefile = file_get_contents('src\php-src\Makefile');
        $makefile = preg_replace('/\$\(BUILD_DIR\)\\\php\.exe:\s[^\r\n]+/m', implode("\r\n\t", self::CLI_TARGET) . "\r\n\r\nnotused:", $makefile);
        if ($this->config->arch !== 'arm64') {
            $makefile .= "\r\n" . '$(BUILD_DIR)\php.exe: $(BUILD_DIR)\Zend\jump_$(FIBER_ASM_ARCH)_ms_pe_masm.obj $(BUILD_DIR)\Zend\make_$(FIBER_ASM_ARCH)_ms_pe_masm.obj' . "\r\n\r\n";
        }
        file_put_contents('src\php-src\Makefile', $makefile);

        // add indirect libs
        $extra_libs = '';
        if ($this->config->getLib('zstd')) {
            $extra_libs .= ' zstd.lib';
        }
        if ($this->config->getLib('brotli')) {
            $extra_libs .= ' brotlidec-static.lib brotlicommon-static.lib';
        }

        file_put_contents('src\php-src\nmake_wrapper.bat', 'nmake /nologo LIBS_CLI="' . $extra_libs . ' ws2_32.lib shell32.lib" %*');

        passthru(
            "cd src\php-src && {$this->config->phpBinarySDKCmd} " .
                '-t nmake_wrapper.bat ' . 
                '--task-args php.exe',
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to make cli");
        }
    }
}
