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

class Libzlib extends Library
{
    use WindowsLibraryTrait;
    protected string $name = 'zlib';
    protected array $staticLibs = [
        //'zlib.lib',
        'zlib_a.lib',
    ];
    protected array $headers = [
        'zlib.h',
        'zconf.h',
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
                    '-DZLIB_BUILD_SHARED=OFF ' .
                    '-DZLIB_BUILD_STATIC=ON ' .
                    '-DCMAKE_INSTALL_PREFIX="' . realpath('deps') . '" ' .
                    "-DCMAKE_TOOLCHAIN_FILE={$this->config->cmakeToolchainFile} " .
                '&& ' .
                "cmake --build builddir --config RelWithDebInfo --target install -j {$this->config->concurrency}",
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to build {$this->name}");
        }

        // zlib 1.3.2 names the win32 static library zs.lib, the shared target
        // z.dll with z.lib as its import library; 1.2.x used zlibstatic.lib,
        // zlib1.dll and zlib.lib. Provide the static names the php sdk and the
        // cmake find module look for and drop the dynamic artifacts
        $staticLib = is_file('deps/lib/zs.lib') ? 'deps/lib/zs.lib' : 'deps/lib/zlibstatic.lib';
        copy($staticLib, 'deps/lib/zlib_a.lib');
        copy($staticLib, 'deps/lib/zlibstatic.lib');
        foreach (['deps/bin/z.dll', 'deps/bin/zlib1.dll', 'deps/bin/zlib.dll', 'deps/lib/z.lib', 'deps/lib/zlib.lib'] as $dynamic) {
            if (is_file($dynamic)) {
                unlink($dynamic);
            }
        }
    }
}
