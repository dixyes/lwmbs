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

class Liblibpng extends Library
{
    use LinuxLibraryTrait;
    protected string $name = 'libpng';
    protected array $staticLibs = [
        'libpng.a',
    ];
    protected array $headers = [
        'png.h',
        'pngconf.h',
        'pnglibconf.h',
    ];
    protected array $pkgconfs = [
        'libpng.pc' => <<<'EOF'
exec_prefix=${prefix}
libdir=${exec_prefix}/lib
includedir=${prefix}/include/libpng16

Name: libpng
Description: Loads and saves PNG files
Version: 1.6.37
Requires: zlib
Libs: -L${libdir} -lpng
Libs.private: -lm -lz -lm 
Cflags: -I${includedir}
EOF,
    ];
    protected array $depNames = [
        'zlib' => false,
    ];

    protected function build(): void
    {
        Log::i("building {$this->name}");

        $optimizations = match ($this->config->arch) {
            'x86_64' => '--enable-intel-sse ',
            'arm64' => '--enable-arm-neon ',
            default => '',
        };

        // patch configure
        $configure = file_get_contents(realpath('./src/libpng/configure'));
        $configure = str_replace('-lz', realpath('lib/libz.a'), $configure);
        file_put_contents(realpath('./src/libpng/configure'), $configure);

        $ret = 0;
        passthru(
            $this->config->setX . ' && ' .
                "cd {$this->sourceDir} && " .
                "{$this->config->configureEnv} " .
                "./configure " .
                "--host={$this->config->arch}-unknown-linux " .
                '--disable-shared ' .
                '--enable-static ' .
                '--enable-hardware-optimizations ' .
                $optimizations .
                '--prefix= && ' . //use prefix=/
                "make clean && " .
                "make -j{$this->config->concurrency} DEFAULT_INCLUDES='-I. -I" . realpath('include') . "' LIBS= libpng16.la && " .
                'make install-libLTLIBRARIES install-data-am DESTDIR=' . realpath('.') . ' && ' .
                'cd ' . realpath('./lib') . ' && ' .
                'ln -sf libpng16.a libpng.a',
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to build {$this->name}");
        }
    }
}
