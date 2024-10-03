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

class Libbzip2 extends Library
{
    use WindowsLibraryTrait;
    protected string $name = 'bzip2';
    protected array $staticLibs = [
        ['libbz2.lib', 'libbz2_a.lib'],
    ];
    protected array $headers = [
        'bzlib.h',
    ];
    protected array $depNames = [
    ];

    protected function build(): void
    {
        Log::i("building {$this->name}");

        $ret = 0;
        passthru(
            "cd {$this->sourceDir} && " .
                'cmake -B builddir ' .
                    "-A \"{$this->config->cmakeArch}\" " .
                    "-G \"{$this->config->cmakeGeneratorName}\" " .
                    "-D ENABLE_LIB_ONLY=ON " .
                    "-D ENABLE_TESTS=OFF " .
                    "-D ENABLE_DOCS=OFF " .
                    "-D ENABLE_SHARED_LIB=OFF " .
                    "-D ENABLE_STATIC_LIB=ON " .
                    '-DCMAKE_INSTALL_PREFIX="' . realpath('deps') . '" ' .
                    "-DCMAKE_TOOLCHAIN_FILE={$this->config->cmakeToolchainFile} " .
                    '&& ' .
                "cmake --build builddir --config RelWithDebInfo --target install -j {$this->config->concurrency}",
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to build {$this->name}");
        }

        copy('deps/lib/bz2_static.lib', 'deps/lib/libbz2.lib');
        copy('deps/lib/bz2_static.lib', 'deps/lib/libbz2_a.lib');
    }
}
