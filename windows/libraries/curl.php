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

class Libcurl extends Library
{
    use WindowsLibraryTrait;
    protected string $name = 'curl';
    protected array $staticLibs = [
        'libcurl.lib',
    ];
    protected array $headers = [
        'curl',
    ];
    protected array $depNames = [
        'zlib' => true,
        'libssh2' => true,
        'brotli' => true,
        'nghttp2' => true,
        'zstd' => true,
        'openssl' => true,
        'idn2' => true,
        'psl' => true,
    ];

    protected function build(): void
    {
        Log::i("building {$this->name}");

        $zlib = 'OFF';
        if ($this->config->getLib('zlib')) {
            $zlib = 'ON';
        }

        $libssh2 = 'OFF';
        if ($this->config->getLib('libssh2')) {
            $libssh2 = 'ON' ;
        }

        $brotli = '-DCURL_BROTLI=OFF ';
        if ($this->config->getLib('brotli')) {
            $brotli = '-DCURL_BROTLI=ON ' .
            '-DBROTLIDEC_LIBRARY="' . realpath('deps/lib/brotlidec-static.lib') . ';' . realpath('deps/lib/brotlicommon-static.lib') . '" ' .
            '-DBROTLICOMMON_LIBRARY="' . realpath('deps/lib/brotlicommon-static.lib') . '" ' .
            '-DBROTLI_INCLUDE_DIR="' . realpath('deps/include') . '" ';
        }

        $nghttp2 = 'OFF';
        if ($this->config->getLib('nghttp2')) {
            $nghttp2 = 'ON';
        }

        $zstd = 'OFF';
        if ($this->config->getLib('zstd')) {
            $zstd = 'ON';
        }

        $idn2 = 'OFF';
        if ($this->config->getLib('idn2')) {
            $idn2 = 'ON';
        }

        $psl = 'OFF';
        if ($this->config->getLib('psl')) {
            $psl = 'ON';
        }

        $ssl = '-DCURL_USE_OPENSSL=OFF -DCURL_USE_SCHANNEL=ON -DCURL_WINDOWS_SSPI=ON';
        if ($this->config->getLib('openssl')) {
            $ssl = '-DCURL_USE_OPENSSL=ON';
        }

        // patch libcurl cmake file to avoid bloat build res file conflict
        $cmakelists = file_get_contents('src/curl/lib/CMakeLists.txt');
        $cmakelists = preg_replace('/\s*list\s*\(\s*APPEND\s+CSOURCES\s+libcurl.rc\s*\)/', '', $cmakelists);
        file_put_contents('src/curl/lib/CMakeLists.txt', $cmakelists);

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
                    '-DBUILD_CURL_EXE=OFF ' .
                    '-DBUILD_SHARED_LIBS=OFF ' .
                    '-DBUILD_STATIC_LIBS=ON ' .
                    (0 ? '-DBUILD_CURL_EXE=ON ' : '-DBUILD_CURL_EXE=OFF ') .
                    '-DCURL_STATIC_CRT=ON ' .
                    '-DENABLE_UNICODE=ON ' .
                    // since libuv things donot support windows 7 any more,
                    // for windows 8 +
                    '-DCURL_TARGET_WINDOWS_VERSION=0x0602 ' .
                    // curl cmakefile bug workaround?
                    '-DCURL_TEST_DEFINES="-DWIN32 -D_WIN32_WINNT=0x0501" ' .
                    "-DUSE_ZLIB=$zlib " .
                    "-DCURL_USE_LIBSSH2=$libssh2 " .
                    "$brotli " .
                    "-DUSE_NGHTTP2=$nghttp2 " .
                    "-DCURL_ZSTD=$zstd " .
                    "-DUSE_LIBIDN2=$idn2 " .
                    "-DCURL_USE_LIBPSL=$psl " .
                    "$ssl " .
                    "-DUSE_WIN32_IDN=ON " .
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

    }
}
