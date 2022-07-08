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

class Liblibzip extends Library
{
    use WindowsLibraryTrait;
    protected string $name = 'libzip';
    protected array $staticLibs = [
        ['zip.lib', 'libzip_a.lib'],
    ];
    protected array $headers = [
        'zip.h',
        'zipconf.h',
    ];
    protected array $depNames = [
        'zlib' => false,
        'bzip2' => true,
        'xz' => true,
        'zstd' => true,
        'openssl' => true,
    ];

    protected function build(): void
    {
        Log::i("building {$this->name}");

        $bzip2 = 'OFF';
        if ($this->config->getLib('bzip2')) {
            $bzip2 = 'ON';
        }

        $xz = 'OFF';
        if ($this->config->getLib('xz')) {
            $xz = 'ON';
        }

        $zstd = 'OFF';
        if ($this->config->getLib('zstd')) {
            $zstd = 'ON';
        }

        $openssl = 'OFF';
        if ($this->config->getLib('openssl')) {
            $openssl = 'ON';
        }

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
                    "-DENABLE_BZIP2=$bzip2 " .
                    "-DENABLE_LZMA=$xz " .
                    "-DENABLE_ZSTD=$zstd " .
                    "-DENABLE_OPENSSL=$openssl " .
                    '-DBUILD_DOC=OFF ' .
                    '-DBUILD_EXAMPLES=OFF ' .
                    '-DBUILD_REGRESS=OFF '.
                    '-DBUILD_TOOLS=OFF '.
                    //'-DCMAKE_C_FLAGS_MINSIZEREL="/MT /O1 /Ob1 /DNDEBUG" ' .
                    '-DCMAKE_INSTALL_PREFIX="'. realpath('deps'). '" ' . 
                    "-DCMAKE_TOOLCHAIN_FILE={$this->config->cmakeToolchainFile} " .
                '&& '.
                "cmake --build builddir --config RelWithDebInfo --target install -j {$this->config->concurrency}",
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to build {$this->name}");
        }

        copy('deps\lib\zip.lib', 'deps\lib\libzip_a.lib');
    }
}
