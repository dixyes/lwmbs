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
    use WindowsLibraryTrait;
    protected string $name = 'libpng';
    protected array $staticLibs = [
        'libpng16_static.lib',
    ];
    protected array $headers = [
        'png.h',
        'pngconf.h',
    ];
    protected array $depNames = [
        'zlib' => false,
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
                    '-DBUILD_SHARED_LIBS=OFF ' .
                    '-DSKIP_INSTALL_PROGRAMS=ON ' .
                    '-DSKIP_INSTALL_FILES=ON ' .
                    '-DPNG_SHARED=OFF ' .
                    '-DPNG_EXECUTABLES=OFF ' .
                    '-DPNG_TESTS=OFF ' .
                    //'-DCMAKE_C_FLAGS_MINSIZEREL="/MT /O1 /Ob1 /DNDEBUG" ' .
                    '-DCMAKE_INSTALL_PREFIX="' . realpath('deps') . '" ' .
                    "-DCMAKE_TOOLCHAIN_FILE={$this->config->cmakeToolchainFile} " .
                '&& ' .
                "cmake --build builddir --config RelWithDebInfo --target install -j {$this->config->concurrency}",
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to build {$this->name}");
        }

        // patch pngconf.h
        // $pngconf_h = file_get_contents('deps\include\pngconf.h');
        // $pngconf_h = preg_replace(
        //     '/#\s*define\s+PNG_DLL_IMPORT\s+__declspec\s*\(\s*dllimport\s*\)/',
        //     '#      define PNG_DLL_IMPORT __cdecl',
        //     $pngconf_h,
        // );
        // file_put_contents('deps\include\pngconf.h', $pngconf_h);

        copy('deps/lib/libpng16_static.lib', 'deps/lib/libpng_a.lib');
    }
}
