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
    use MacOSLibraryTrait;
    protected string $name = 'libssh2';
    protected array $staticLibs = [
        'libssh2.a',
    ];
    protected array $headers = [
        'libssh2.h',
        'libssh2_publickey.h',
        'libssh2_sftp.h',
    ];
    protected array $depNames = [
        'zlib' => true,
        'openssl' => false,
    ];

    protected function build(): void
    {
        Log::i("building {$this->name}");
        $libopenssl = $this->config->getLib('openssl');
        if (!$libopenssl) {
            throw new Exception('libssh2 requires openssl');
        }
    
        $ret = 0;

        passthru(
            $this->config->setX . ' && ' .
                "cd {$this->sourceDir} && " .
                'rm -rf build && ' .
                'mkdir -p build && ' .
                'cd build && ' .
                "{$this->config->configureEnv} " . ' cmake ' .
                // '--debug-find ' .
                '-DCMAKE_BUILD_TYPE=Release ' .
                '-DBUILD_SHARED_LIBS=OFF ' .
                '-DBUILD_EXAMPLES=OFF ' .
                '-DBUILD_TESTING=OFF ' .
                '-DCMAKE_INSTALL_PREFIX=/ ' .
                '-DCMAKE_INSTALL_LIBDIR=/lib ' .
                '-DCMAKE_INSTALL_INCLUDEDIR=/include ' .
                "-DCMAKE_TOOLCHAIN_FILE={$this->config->cmakeToolchainFile} " .
                '.. && ' .
                "cmake --build . -j {$this->config->concurrency} --target libssh2 && " .
                'make install DESTDIR="' . realpath('.') . '"' ,
            $ret
        );

        if ($ret !== 0) {
            throw new Exception("failed to build {$this->name}");
        }
    }
}
