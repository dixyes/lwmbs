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
    use WindowsLibraryTrait;
    protected string $name = 'libjpegturbo';
    protected array $staticLibs = [
        'libjpeg_a.lib',
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
        if (is_dir("{$this->sourceDir}\\builddir")) {
            exec("rmdir /s /q \"{$this->sourceDir}\\builddir\"", result_code: $ret);
            if ($ret !== 0) {
                throw new Exception("failed to clean up {$this->name}");
            }
        }
        passthru(
            "cd {$this->sourceDir} && " .
                'cmake -B builddir ' .
                    "-A \"{$this->config->cmakeArch}\" " .
                    "-G \"{$this->config->cmakeGeneratorName}\" " .
                    '-DENABLE_SHARED=OFF ' .
                    '-DBUILD_SHARED_LIBS=OFF ' .
                    '-DWITH_JAVA=OFF ' .
                    '-DWITH_CRT_DLL=OFF ' .
                    '-DCMAKE_INSTALL_PREFIX="' . realpath('deps') . '" ' .
                    "-DCMAKE_TOOLCHAIN_FILE={$this->config->cmakeToolchainFile} " .
                '&& ' .
                "cmake --build builddir --config RelWithDebInfo --target install -j {$this->config->concurrency}",
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to build {$this->name}");
        }

        copy('deps/lib/jpeg-static.lib', 'deps/lib/libjpeg_a.lib');
    }
}
