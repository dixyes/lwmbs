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

class Liblibxml2 extends Library
{
    use WindowsLibraryTrait;
    protected string $name = 'libxml2';
    protected array $staticLibs = [
        [ 'libxml2s.lib', 'libxml2_a.lib' ]
    ];
    protected array $headers = [
        'libxml2',
    ];
    protected array $depNames = [
        "icu" => true,
        "xz" => true,
        "zlib" => true,
        "pthreads4w" => true,
    ];

    protected function build(): void
    {
        Log::i("building {$this->name}");

        // patch libxml2 cmake file to avoid bloat build res file conflict
        $cmakelists = file_get_contents('src/libxml2/CMakeLists.txt');
        $cmakelists = preg_replace('|\s*list\s*\(\s*APPEND\s+LIBXML2_SRCS\s+win32/libxml2\.rc\s*\)|', '', $cmakelists);
        file_put_contents('src/libxml2/CMakeLists.txt', $cmakelists);

        $enable_zlib = 'OFF';
        if ($this->config->getLib('zlib')) {
            $enable_zlib = 'ON';
        }

        $enable_icu = 'OFF';
        if ($this->config->getLib('icu')) {
            $enable_icu = 'ON';
        }

        $enable_xz = 'OFF';
        if ($this->config->getLib('xz')) {
            $enable_xz = 'ON';
        }

        $enable_pthreads = 'OFF';
        if ($this->config->getLib('pthreads4w')) {
            $enable_pthreads = 'ON';
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
                '-DLIBXML2_WITH_ICONV=OFF ' .
                "-DLIBXML2_WITH_ZLIB=$enable_zlib " .
                "-DLIBXML2_WITH_ICU=$enable_icu " .
                "-DLIBXML2_WITH_LZMA=$enable_xz " .
                "-DLIBXML2_WITH_THREADS=$enable_pthreads " .
                '-DLIBXML2_WITH_PYTHON=OFF ' .
                '-DLIBXML2_WITH_PROGRAMS=OFF ' .
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

        copy('deps\lib\libxml2s.lib', 'deps\lib\libxml2_a.lib');
    }
}
