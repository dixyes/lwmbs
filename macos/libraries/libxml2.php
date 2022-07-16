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

class Liblibxml2 extends Library
{
    use MacOSLibraryTrait;
    protected string $name = 'libxml2';
    protected array $staticLibs = [
        'libxml2.a',
    ];
    protected array $headers = [
        'libxml2',
    ];
    protected array $depNames = [
        "icu" => true,
        "xz" => true,
        "zlib" => true,
    ];

    protected function build(): void
    {
        Log::i("building {$this->name}");

        $enable_zlib = 'OFF';
        if ($this->config->getLib('zlib')) {
            $enable_zlib = 'ON';
        }

        $enable_icu = 'OFF';
        if ($this->config->getLib('icu')) {
            $enable_icu = 'ON';
        }

        $enable_xz = 'OFF';
        if ($this->config->getLib('xz')) {
            $enable_xz = 'ON';
        }

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
                '-DLIBXML2_WITH_ICONV=ON ' .
                "-DLIBXML2_WITH_ZLIB=$enable_zlib " .
                "-DLIBXML2_WITH_ICU=$enable_icu " .
                "-DLIBXML2_WITH_LZMA=$enable_xz " .
                '-DLIBXML2_WITH_PYTHON=OFF ' .
                '-DLIBXML2_WITH_PROGRAMS=OFF ' .
                '-DLIBXML2_WITH_TESTS=OFF ' .
                '-DCMAKE_INSTALL_PREFIX=/ ' .
                '-DCMAKE_INSTALL_LIBDIR=/lib ' .
                '-DCMAKE_INSTALL_INCLUDEDIR=/include ' .
                "-DCMAKE_TOOLCHAIN_FILE={$this->config->cmakeToolchainFile} " .
                '.. && ' .
                "cmake --build . -j {$this->config->concurrency} && " .
                'make install DESTDIR="' . realpath('.') . '"',
            $ret
        );

        if ($ret !== 0) {
            throw new Exception("failed to build {$this->name}");
        }
    }
}
