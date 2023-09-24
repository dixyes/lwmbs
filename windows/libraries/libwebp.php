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

class Liblibwebp extends Library
{
    use WindowsLibraryTrait;
    protected string $name = 'libwebp';
    protected array $staticLibs = [
        'libwebp.lib',
        'libsharpyuv.lib'
    ];
    protected array $headers = [
        'webp',
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
                    '-DBUILD_SHARED_LIBS=OFF ' .
                    '-DWEBP_LINK_STATIC=ON ' .
                    '-DWEBP_ENABLE_SIMD=ON ' .
                    '-DWEBP_BUILD_ANIM_UTILS=OFF ' .
                    '-DWEBP_BUILD_CWEBP=OFF ' .
                    '-DWEBP_BUILD_DWEBP=OFF ' .
                    '-DWEBP_BUILD_GIF2WEBP=OFF ' .
                    '-DWEBP_BUILD_IMG2WEBP=OFF ' .
                    '-DWEBP_BUILD_VWEBP=OFF ' .
                    '-DWEBP_BUILD_WEBPINFO=OFF ' .
                    '-DWEBP_BUILD_LIBWEBPMUX=OFF ' .
                    '-DWEBP_BUILD_WEBPMUX=OFF ' .
                    '-DWEBP_BUILD_EXTRAS=OFF ' .
                    '-DWEBP_UNICODE=ON ' .
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
