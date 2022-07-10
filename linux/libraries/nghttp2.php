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
    use LinuxLibraryTrait;
    protected string $name = 'nghttp2';
    protected array $staticLibs = [
        'libnghttp2.a',
    ];
    protected array $headers = [
        'nghttp2',
    ];
    protected array $pkgconfs = [
        'libnghttp2.pc' => <<<'EOF'
exec_prefix=${prefix}
libdir=${exec_prefix}/lib
includedir=${prefix}/include

Name: libnghttp2
Description: HTTP/2 C library
URL: https://github.com/tatsuhiro-t/nghttp2
Version: 1.47.0
Libs: -L${libdir} -lnghttp2
Cflags: -I${includedir}
EOF
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

        $args = $this->config->makeAutoconfArgs($this->name, [
            'zlib' => null,
            'openssl' => null,
            'libxml2' => null,
            'libev' => null,
            'libcares' => null,
            'libngtcp2' => null,
            'libnghttp3' => null,
            'libbpf' => null,
            'libevent-openssl' => null,
            'jansson' => null,
            'jemalloc' => null,
            'systemd' => null,
            'cunit' => null,
        ]);

        passthru(
            $this->config->setX . ' && ' .
                "cd {$this->sourceDir} && " .
                "{$this->config->configureEnv} " . ' ./configure ' .
                '--enable-static ' .
                '--disable-shared ' .
                "--host={$this->config->arch}-unknown-linux " .
                '--enable-lib-only ' .
                '--with-boost=no ' .
                $args . ' ' .
                '--prefix= && ' . //use prefix=/
                "make clean && " .
                "make -j{$this->config->concurrency} && " .
                'make install DESTDIR=' . realpath('.'),
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to build {$this->name}");
        }
    }
}
