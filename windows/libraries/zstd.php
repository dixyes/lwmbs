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

class Libzstd extends Library
{
    use WindowsLibraryTrait;
    protected string $name = 'zstd';
    protected array $staticLibs = [
        ['zstd.lib', 'zstd_static.lib'],
    ];
    protected array $headers = [
        'zstd.h',
        'zstd_errors.h',
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
                'cmake build/cmake -B builddir ' .
                    "-A \"{$this->config->cmakeArch}\" " .
                    "-G \"{$this->config->cmakeGeneratorName}\" " .
                    '-DCMAKE_BUILD_TYPE=RelWithDebInfo ' .
                    '-DBUILD_TESTING=OFF ' .
                    (0 ? '-DZSTD_BUILD_PROGRAMS=ON ' : '-DZSTD_BUILD_PROGRAMS=OFF ') .
                    '-DZSTD_BUILD_STATIC=ON ' .
                    '-DZSTD_BUILD_SHARED=OFF ' .
                    '-DZSTD_USE_STATIC_RUNTIME=ON ' .
                    '-DZSTD_PROGRAMS_LINK_SHARED=OFF ' .
                    //'-DCMAKE_C_FLAGS_MINSIZEREL="/MT /O1 /Ob1 /DNDEBUG" ' .
                    '-DCMAKE_INSTALL_PREFIX="' . realpath('deps') . '" ' .
                    "-DCMAKE_TOOLCHAIN_FILE={$this->config->cmakeToolchainFile} " .
                '&& ' .
                //--config MinSizeRel 
                "cmake --build builddir --config RelWithDebInfo --target install -j {$this->config->concurrency}",
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to build {$this->name}");
        }

        copy('deps/lib/zstd_static.lib', 'deps/lib/zstd.lib');
    }
}
