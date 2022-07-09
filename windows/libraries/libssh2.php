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

class Liblibssh2 extends Library
{
    use WindowsLibraryTrait;
    protected string $name = 'libssh2';
    protected array $staticLibs = [
        'libssh2.lib',
    ];
    protected array $headers = [
        'libssh2.h',
        'libssh2_publickey.h',
        'libssh2_sftp.h',
    ];
    protected array $depNames = [
        'zlib' => true,
        'openssl' => true,
    ];

    protected function build(): void
    {
        Log::i("building {$this->name}");

        $enable_zlib = 'OFF';
        $zlib = $this->config->getLib('zlib');
        if ($zlib) {
            $enable_zlib = 'ON';
        }

        // patch libssh2 cmake file to avoid bloat build res file conflict
        $cmakelists = file_get_contents('src/libssh2/src/CMakeLists.txt');
        $cmakelists = preg_replace('|\s*list\s*\(\s*APPEND\s+SOURCES\s+\$\{PROJECT_SOURCE_DIR\}/win32/libssh2\.rc\s*\)|', '', $cmakelists);
        file_put_contents('src/libssh2/src/CMakeLists.txt', $cmakelists);
        
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
                    '-DBUILD_EXAMPLES=OFF ' .
                    (1 ? '-DBUILD_TESTING=ON ' : '-DBUILD_TESTING=OFF ') .
                    "-DENABLE_ZLIB_COMPRESSION=$enable_zlib " .
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
    }
}
