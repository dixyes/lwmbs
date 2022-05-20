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
    use LinuxLibraryTrait;
    protected string $name = 'libssh2';
    protected array $staticLibs = [
        'libssh2.a',
    ];
    protected array $headers = [
        'libssh2.h',
        'libssh2_publickey.h',
        'libssh2_sftp.h',
    ];
    protected array $pkgconfs = [
        'libssh2.pc' => <<<'EOF'
exec_prefix=${prefix}
libdir=${exec_prefix}/lib
includedir=${prefix}/include

Name: libssh2
URL: https://www.libssh2.org/
Description: Library for SSH-based communication
Version: 1.10.0
Requires.private: libssl libcrypto zlib
Libs: -L${libdir} -lssh2
Libs.private:
Cflags: -I${includedir}
EOF
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

        $libs = 'LIBS="' . $libopenssl->getStaticLibFiles() . ' ';

        $zlib = '';
        $libzlib = $this->config->getLib('zlib');
        if ($libzlib) {
            $zlib = '--with-libz --with-libz-prefix=' . realpath('.');
            $libs .= $libzlib->getStaticLibFiles() . ' ';
        }

        $libs = rtrim($libs) . ' -lpthread -ldl"';

        passthru(
            $this->config->setX . ' && ' .
                "cd {$this->sourceDir} && " .
                "{$this->config->configureEnv} " . $this->config->libc->getCCEnv() . ' ./configure ' .
                '--enable-static ' .
                '--disable-shared ' .
                '--disable-rpath ' .
                '--with-crypto=openssl ' .
                '--with-libssl-prefix=' . realpath('.') . ' ' .
                $zlib . ' ' .
                $libs . ' ' .
                '--prefix= && ' . //use prefix=/
                "make -j {$this->config->concurrency} && " .
                'make install DESTDIR=' . realpath('.'),
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to build {$this->name}");
        }
    }
}
