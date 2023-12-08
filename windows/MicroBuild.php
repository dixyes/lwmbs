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

    public function build(bool $fresh = false, bool $bloat = false, bool $fakeCli = false): void
    {
        Log::i("building micro");

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
            throw new Exception("failed to buildconf for micro");
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
                '--disable-cli ' .
                '--enable-micro ' .
                ($this->config->zts ? '--enable-zts' : '') . ' ' .
                $this->config->makeExtensionArgs() . '"',
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to configure micro");
        }

        // if ($this->config->arch === 'arm64') {
        //     // workaround for InterlockedExchange8 missing (seems to be a MSVC bug)
        //     $zend_atomic = file_get_contents('src\php-src\Zend\zend_atomic.h');
        //     $zend_atomic = preg_replace('/\bInterlockedExchange8\b/', '_InterlockedExchange8', $zend_atomic);
        //     file_put_contents('src\php-src\Zend\zend_atomic.h', $zend_atomic);
        // }

        // workaround for fiber
        $makefile = file_get_contents('src\php-src\Makefile');
        if ($this->config->arch !== 'arm64' && str_contains($makefile, 'FIBER_ASM_ARCH')) {
            $makefile .= "\r\n" . '$(MICRO_SFX): $(BUILD_DIR)\Zend\jump_$(FIBER_ASM_ARCH)_ms_pe_masm.obj $(BUILD_DIR)\Zend\make_$(FIBER_ASM_ARCH)_ms_pe_masm.obj' . "\r\n\r\n";
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
            $extra_libs .= ' wldap32.lib';
        }
        if ($this->config->getLib('libwebp')) {
            $extra_libs .= ' libsharpyuv.lib';
        }

        $nmake_wrapper_bat_contents =
            'nmake /nologo LIBS_MICRO="' . $extra_libs . ' ws2_32.lib shell32.lib"'.
                ($fakeCli ? ' CFLAGS_MICRO="/DZEND_ENABLE_STATIC_TSRMLS_CACHE=1 /DPHP_MICRO_FAKE_CLI"' : '') .
            ' %*';
        file_put_contents('src\php-src\nmake_wrapper.bat', $nmake_wrapper_bat_contents);

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

        if ($this->config->getExt('phar')) {
            $pharPatched = true;
            passthru(
                "cd src\\php-src && patch -p1 < sapi/micro/patches/phar.patch",
                $ret
            );
            if ($ret !== 0) {
                Log::e("failed to patch phar");
                $pharPatched = false;
            }
        }

        passthru(
            "cd src\\php-src && {$this->config->phpBinarySDKCmd} " .
                '-t nmake_wrapper.bat ' . 
                '--task-args micro',
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to make micro");
        }

        if (match(php_uname('m')) {
            'AMD64' => 'x64',
            'ARM64' => 'arm64',
        } === $this->config->arch) {
            Log::i('running sanity check');
            file_put_contents(
                'hello.exe',
                file_get_contents("src\\php-src\\{$this->config->arch}\\Release_TS\\micro.sfx") . '<?php echo "hello";'
            );
            exec(
                'hello.exe',
                $output,
                $ret
            );
            if ($ret !== 0 || trim(implode('', $output)) !== 'hello') {
                throw new Exception("micro failed sanity check");
            }
        }

        if ($this->config->getExt('phar') && $pharPatched) {
            passthru(
                "cd src\\php-src && patch -p1 -R < sapi/micro/patches/phar.patch",
                $ret
            );
            if ($ret !== 0) {
                throw new Exception("failed to recover phar patch");
            }
        }
    }
}
