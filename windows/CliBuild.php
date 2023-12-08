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
        '"$(LINK)" /nologo $(PHP_GLOBAL_OBJS_RESP) $(CLI_GLOBAL_OBJS_RESP) $(STATIC_EXT_OBJS_RESP) $(STATIC_EXT_LIBS) $(ASM_OBJS) $(LIBS) $(LIBS_CLI) $(BUILD_DIR)\php.exe.res /out:$(BUILD_DIR)\php.exe $(LDFLAGS) $(LDFLAGS_CLI) /ltcg /nodefaultlib:msvcrt /nodefaultlib:msvcrtd /ignore:4286',
        '-@$(_VC_MANIFEST_EMBED_EXE)',
    ];

    public function __construct(
        private Config $config
    ) {
    }

    public function build(bool $fresh = false, bool $bloat = false): void
    {
        Log::i("building cli");

        $ret = 0;
    
        Util::patchLibxml();
        if ($this->config->getExt('zstd')) {
            Util::zstdAPCufix();
        }
        Util::patchGD();

        passthru(
            "cd src\\php-src && {$this->config->phpBinarySDKCmd} -t buildconf.bat",
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to buildconf for cli");
        }

        passthru(
            "cd src\\php-src && {$this->config->phpBinarySDKCmd} " .
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
                $this->config->makeExtensionArgs() . '"',
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to configure cli");
        }

        // workaround for static cli build (needs cli_static.patch from micro also)
        $makefile = file_get_contents('src\php-src\Makefile');
        $makefile = preg_replace('/\$\(BUILD_DIR\)\\\php\.exe:\s[^\r\n]+/m', implode("\r\n\t", self::CLI_TARGET) . "\r\n\r\nnotused:", $makefile);
        if ($this->config->arch !== 'arm64' && str_contains($makefile, 'FIBER_ASM_ARCH')) {
            $makefile .= "\r\n" . '$(BUILD_DIR)\php.exe: $(BUILD_DIR)\Zend\jump_$(FIBER_ASM_ARCH)_ms_pe_masm.obj $(BUILD_DIR)\Zend\make_$(FIBER_ASM_ARCH)_ms_pe_masm.obj' . "\r\n\r\n";
        }
        file_put_contents('src\php-src\Makefile', $makefile);

        // add extra libs
        $extra_libs = '';
        if ($bloat) {
            Log::i('bloat linking');
            $bloat_libs = [];
            foreach ($this->config->makeLibArray() as $lib) {
                array_push($bloat_libs, ...$lib->getStaticLibs());
            }
            $extra_libs .= ' ' . implode(' ', array_map(fn($x)=>"/WHOLEARCHIVE:$x $x",$bloat_libs));
            $makefile = str_replace('/opt:ref,icf', '/opt:noref', $makefile);
            file_put_contents('src\php-src\Makefile', $makefile);
        } else {
            // add indirect libs only
            if ($this->config->getLib('zstd')) {
                $extra_libs .= ' zstd.lib';
            }
            if ($this->config->getLib('brotli')) {
                $extra_libs .= ' brotlidec-static.lib brotlicommon-static.lib';
            }
            if ($this->config->getLib('webview')) {
                $extra_libs .= ' /WHOLEARCHIVE:webview_static.lib WebView2LoaderStatic.lib';
            }
        }
        if ($this->config->getLib('openssl')) {
            $extra_libs .= ' crypt32.lib';
        }
        if ($this->config->getLib('curl')) {
            $extra_libs .= ' wldap32.lib normaliz.lib';
        }
        if ($this->config->getLib('libwebp')) {
            $extra_libs .= ' libsharpyuv.lib';
        }

        file_put_contents('src\php-src\nmake_wrapper.bat',
            'nmake /nologo LIBS_CLI="' . $extra_libs . ' ws2_32.lib shell32.lib" EXTRA_LD_FLAGS_PROGRAM= %*');

        if ($fresh) {
            Log::i('cleanning up');
            passthru(
                "cd src\\php-src && {$this->config->phpBinarySDKCmd} " .
                    '-t nmake_wrapper.bat ' . 
                    '--task-args clean'
                ,
                $ret
            );
        }
    
        passthru(
            "cd src\\php-src && {$this->config->phpBinarySDKCmd} " .
                '-t nmake_wrapper.bat ' . 
                '--task-args php.exe',
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to make cli");
        }

        if (match(php_uname('m')) {
            'AMD64' => 'x64',
            'ARM64' => 'arm64',
        } === $this->config->arch) {
            Log::i('running sanity check');
            exec(
                'cd src\php-src && ' .
                "{$this->config->arch}\\Release_TS\\php.exe -r \"echo \\\"hello\\\";\"",
                $output,
                $ret
            );
            if ($ret !== 0 || trim(implode('', $output)) !== 'hello') {
                throw new Exception("cli failed sanity check");
            }
        }
    }
}
