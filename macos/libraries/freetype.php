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

class Libfreetype extends Library
{
    use MacOSLibraryTrait;
    protected string $name = 'freetype';
    protected array $staticLibs = [
        'libfreetype.a',
    ];
    protected array $headers = [
        'freetype2',
    ];
    protected array $depNames = [
        'zlib' => false,
        'bzip2' => true,
        'libpng' => true,
        'harfbuzz' => true,
        'brotli' => true,
    ];

    protected function build(): void
    {
        Log::i("building {$this->name}");
        
        $ret = 0;
        if (is_dir("{$this->sourceDir}/builddir")) {
            exec("rm -rf \"{$this->sourceDir}/builddir\"", result_code: $ret);
            if ($ret !== 0) {
                throw new Exception("failed to clean up {$this->name}");
            }
        }

        $bzip2 = '-DFT_DISABLE_BZIP2=ON ';
        if ($this->config->getLib('bzip2')) {
            $bzip2 = '-DFT_DISABLE_BZIP2=OFF -DFT_REQUIRE_BZIP2=ON ';
        }

        $libpng = '-DFT_DISABLE_PNG=ON ';
        if ($this->config->getLib('libpng')) {
            $libpng = '-DFT_DISABLE_PNG=OFF -DFT_REQUIRE_PNG=ON ';
        }

        $harfbuzz = '-DFT_DISABLE_HARFBUZZ=ON ';
        if ($this->config->getLib('harfbuzz')) {
            $harfbuzz = '-DFT_DISABLE_HARFBUZZ=OFF -DFT_REQUIRE_HARFBUZZ=ON ';
        }

        $brotli = '-DFT_DISABLE_BROTLI=ON ';
        if ($this->config->getLib('brotli')) {
            $brotli = '-DFT_DISABLE_BROTLI=OFF ' .
                '-DFT_REQUIRE_BROTLI=ON ' .
                '-DBROTLIDEC_LIBRARIES="' . realpath('lib/libbrotlidec-static.a') . ';' . realpath('lib/libbrotlicommon-static.a') . '" ' .
                '-DBROTLIDEC_INCLUDE_DIRS="' . realpath('include') . '" ';
        }

        passthru(
            "cd {$this->sourceDir} && " .
                "{$this->config->configureEnv} " . 'cmake -B builddir ' .
                    '-DBUILD_SHARED_LIBS=OFF ' .
                    $bzip2 . ' ' .
                    $harfbuzz . ' ' .
                    $libpng . ' ' .
                    $brotli . ' ' .
                    '-DCMAKE_INSTALL_PREFIX=/ ' .
                    '-DCMAKE_INSTALL_LIBDIR=/lib ' .
                    '-DCMAKE_INSTALL_INCLUDEDIR=/include ' .
                    "-DCMAKE_TOOLCHAIN_FILE={$this->config->cmakeToolchainFile} " .
                '&& ' .
                "cmake --build builddir -j {$this->config->concurrency} && " .
                'make -C builddir install DESTDIR="' . realpath('.') . '"',
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to build {$this->name}");
        }
    }
}
