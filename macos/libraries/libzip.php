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
    use MacOSLibraryTrait;
    protected string $name = 'libzip';
    protected array $staticLibs = [
        'libzip.a',
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
    ];

    protected function build(): void
    {
        Log::i("building {$this->name}");
        $ret = 0;

        $zlib = '';
        $libzlib = $this->config->getLib('zlib');
        if ($libzlib) {
            $zlib = '-DZLIB_LIBRARY="' . $libzlib->getStaticLibFiles(style: 'cmake') . '" ' .
                '-DZLIB_INCLUDE_DIR=' . realpath('include') . ' ';
        }

        $bzip2 = '-DENABLE_BZIP2=OFF ';
        $libbzip2 = $this->config->getLib('bzip2');
        if ($libbzip2) {
            $bzip2 = '-DENABLE_BZIP2=ON ' .
                '-DBZIP2_LIBRARIES="' . $libbzip2->getStaticLibFiles(style: 'cmake') . '" ' .
                '-DBZIP2_INCLUDE_DIR=' . realpath('include') . ' ';
        }

        $xz = '-DENABLE_LZMA=OFF ';
        $libxz = $this->config->getLib('xz');
        if ($libxz) {
            $xz = '-DENABLE_LZMA=ON ' .
                '-DLIBLZMA_LIBRARY="' . $libxz->getStaticLibFiles(style: 'cmake') . '" ' .
                '-DLIBLZMA_INCLUDE_DIR=' . realpath('include') . ' ';
        }

        $zstd = '-DENABLE_ZSTD=OFF ';
        $libzstd = $this->config->getLib('zstd');
        if ($libzstd) {
            // TODO: enable it
            $zstd = '-DENABLE_ZSTD=ON ' .
                '-DZstd_LIBRARY="' . $libzstd->getStaticLibFiles(style: 'cmake') . '" ' .
                '-DZstd_INCLUDE_DIR="' . realpath('include') . '" ';
        }

        passthru(
            $this->config->setX . ' && ' .
                "cd {$this->sourceDir} && " .
                'rm -rf build && ' .
                'mkdir -p build && ' .
                'cd build && ' .
                "{$this->config->configureEnv} " . ' cmake ' .
                // '--debug-find ' .
                '-DCMAKE_BUILD_TYPE=Release ' .
                '-DENABLE_GNUTLS=OFF ' .
                '-DENABLE_OPENSSL=ON ' .
                '-DENABLE_MBEDTLS=OFF ' .
                '-DBUILD_SHARED_LIBS=OFF ' .
                '-DBUILD_DOC=OFF ' .
                '-DBUILD_EXAMPLES=OFF ' .
                '-DBUILD_REGRESS=OFF ' .
                '-DBUILD_TOOLS=OFF ' .
                $bzip2 .
                $xz .
                $zstd .
                $zlib .
                '-DCMAKE_INSTALL_PREFIX=/ ' .
                '-DCMAKE_INSTALL_LIBDIR=/lib ' .
                '-DCMAKE_INSTALL_INCLUDEDIR=/include ' .
                "-DCMAKE_TOOLCHAIN_FILE={$this->config->cmakeToolchainFile} " .
                '.. && ' .
                "make -j{$this->config->concurrency} && " .
                'make install DESTDIR=' . realpath('.'),
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to build {$this->name}");
        }
    }
}
