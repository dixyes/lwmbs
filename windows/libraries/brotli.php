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
    use WindowsLibraryTrait;
    protected string $name = 'brotli';
    protected array $staticLibs = [
        'brotlicommon-static.lib',
        'brotlienc-static.lib',
        'brotlidec-static.lib',
    ];
    protected array $headers = [
        'brotli',
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
                    '-DCMAKE_BUILD_TYPE=Release ' .
                    '-DBUILD_SHARED_LIBS=OFF ' .
                    '-DBROTLI_DISABLE_TESTS=ON ' .
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

        rename('deps/lib/brotlicommon.lib', 'deps/lib/brotlicommon-static.lib');
        rename('deps/lib/brotlienc.lib', 'deps/lib/brotlienc-static.lib');
        rename('deps/lib/brotlidec.lib', 'deps/lib/brotlidec-static.lib');
        @unlink('deps/bin/brotli.exe');
        @unlink('deps/bin/brotlicommon.dll');
        @unlink('deps/bin/brotlidec.dll');
        @unlink('deps/bin/brotlienc.dll');
    }
}
