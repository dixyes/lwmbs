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
    use LinuxLibraryTrait;
    protected string $name = 'libwebp';
    protected array $staticLibs = [
        'libwebp.a',
        'libsharpyuv.a',
    ];
    protected array $headers = [
        'webp',
    ];
    protected array $pkgconfs = [
        'libwebp.pc' => <<<'EOF'
exec_prefix=${prefix}
libdir=${exec_prefix}/lib
includedir=${prefix}/include

Name: libwebp
Description: Library for the WebP graphics format
Version: 1.3.1
Requires.private: libsharpyuv
Cflags: -I${includedir}
Libs: -L${libdir} -lwebp
Libs.private: -lm
EOF,
        'libsharpyuv.pc' => <<<'EOF'
exec_prefix=${prefix}
libdir=${exec_prefix}/lib
includedir=${prefix}/include/webp

Name: libsharpyuv
Description: Library for sharp RGB to YUV conversion
Version: 1.3.1
Cflags: -I${includedir}
Libs: -L${libdir} -lsharpyuv
Libs.private: -lm
EOF,
    ];
    protected array $depNames = [
    ];

    protected function build(): void
    {
        Log::i("building {$this->name}");
        
        $ret = 0;
        if (is_dir("{$this->sourceDir}/builddir")) {
            exec("rm -rf \"{$this->sourceDir}/builddir\"", result_code: $ret);
            if ($ret !== 0) {
                throw new Exception("failed to clean up {$this->name}");
            }
        }

        passthru(
            "cd {$this->sourceDir} && " .
                "{$this->config->configureEnv} " . ' cmake -B builddir ' .
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
                    '-DCMAKE_INSTALL_PREFIX=/ ' .
                    '-DCMAKE_INSTALL_LIBDIR=/lib ' .
                    '-DCMAKE_INSTALL_INCLUDEDIR=/include ' .
                    "-DCMAKE_TOOLCHAIN_FILE={$this->config->cmakeToolchainFile} " .
                '&& ' .
                "cmake --build builddir -j {$this->config->concurrency} && " .
                'make -C builddir install DESTDIR="' . realpath('.') . '"',
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to build {$this->name}");
        }
    }
}
