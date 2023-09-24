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

class Liblibjpegturbo extends Library
{
    use MacOSLibraryTrait;
    protected string $name = 'libjpegturbo';
    protected array $staticLibs = [
        'libjpeg.a',
    ];
    protected array $headers = [
        'jpeglib.h',
        'jerror.h',
    ];
    protected array $depNames = [
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
        passthru(
            "cd {$this->sourceDir} && " .
                "{$this->config->configureEnv} " . ' cmake -B builddir ' .
                    '-DENABLE_SHARED=OFF ' .
                    '-DBUILD_SHARED_LIBS=OFF ' .
                    '-DWITH_JAVA=OFF ' .
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
