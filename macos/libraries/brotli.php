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

class Libbrotli extends Library
{
    use MacOSLibraryTrait;
    protected string $name = 'brotli';
    protected array $staticLibs = [
        'libbrotlidec-static.a',
        'libbrotlienc-static.a',
        'libbrotlicommon-static.a',
    ];
    protected array $headers = [
        'brotli'
    ];
    protected array $depNames = [];

    protected function build(): void
    {
        Log::i("building {$this->name}");
        $ret = 0;
        passthru(
            $this->config->setX . ' && ' .
                "cd {$this->sourceDir} && " .
                'rm -rf build && ' .
                'mkdir -p build && ' .
                'cd build && ' .
                "{$this->config->configureEnv} " . ' cmake ' .
                // '--debug-find ' .
                '-DCMAKE_BUILD_TYPE=Release ' .
                '-DBUILD_SHARED_LIBS=OFF ' .
                '-DCMAKE_INSTALL_PREFIX=/ ' .
                '-DCMAKE_INSTALL_LIBDIR=/lib ' .
                '-DCMAKE_INSTALL_INCLUDEDIR=/include ' .
                "-DCMAKE_TOOLCHAIN_FILE={$this->config->cmakeToolchainFile} " .
                '.. && ' .
                "cmake --build . -j {$this->config->concurrency} && " .
                'make install DESTDIR=' . realpath('.'),
            $ret
        );

        rename("lib/libbrotlicommon.a", "lib/libbrotlicommon-static.a");
        rename("lib/libbrotlienc.a", "lib/libbrotlienc-static.a");
        rename("lib/libbrotlidec.a", "lib/libbrotlidec-static.a");
        if ($ret !== 0) {
            throw new Exception("failed to build {$this->name}");
        }
    }
}
