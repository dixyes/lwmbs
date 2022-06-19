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

class Libnghttp2 extends Library
{
    use WindowsLibraryTrait;
    protected string $name = 'nghttp2';
    protected array $staticLibs = [
        'nghttp2.lib',
    ];
    protected array $headers = [
        'nghttp2',
    ];
    protected array $depNames = [
        'zlib' => false,
        'openssl' => false,
        'libxml2' => true,
        'libev' => true,
        'libcares' => true,
        'libngtcp2' => true,
        'libnghttp3' => true,
        'libbpf' => true,
        'libevent-openssl' => true,
        'jansson' => true,
        'jemalloc' => true,
        'systemd' => true,
        'cunit' => true,
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
                    '-DENABLE_STATIC_LIB=ON ' .
                    '-DENABLE_SHARED_LIB=OFF ' .
                    '-DENABLE_STATIC_CRT=ON ' .
                    ((0 && 0/* TODO: zstd+openssl+libev support */) ? '-DENABLE_APP=ON -DENABLE_LIB_ONLY=OFF ' : '-DENABLE_LIB_ONLY=ON ') .
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
        // patch header for missing NGHTTP2_STATICLIB
        $nghttp2_h = file_get_contents('deps/include/nghttp2/nghttp2.h');
        $nghttp2_h = str_replace('#ifdef NGHTTP2_STATICLIB', '#if 1', $nghttp2_h);
        file_put_contents('deps/include/nghttp2/nghttp2.h', $nghttp2_h);
    }
}
