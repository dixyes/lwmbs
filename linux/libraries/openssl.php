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

class Libopenssl extends Library
{
    use LinuxLibraryTrait;
    protected string $name = 'openssl';
    protected array $staticLibs = [
        'libssl.a',
        'libcrypto.a',
    ];
    protected array $headers = [
        'openssl',
    ];
    protected array $pkgconfs = [
        'openssl.pc' => <<<'EOF'
exec_prefix=${prefix}
libdir=${prefix}/lib
includedir=${prefix}/include

Name: OpenSSL
Description: Secure Sockets Layer and cryptography libraries and tools
Version: 3.0.3
Requires: libssl libcrypto
EOF,
        "libssl.pc" => <<<'EOF'
exec_prefix=${prefix}
libdir=${prefix}/lib
includedir=${prefix}/include

Name: OpenSSL-libssl
Description: Secure Sockets Layer and cryptography libraries
Version: 3.0.3
Requires.private: libcrypto
Libs: -L${libdir} -lssl
Cflags: -I${includedir}
EOF,
        "libcrypto.pc" => <<<'EOF'
exec_prefix=${prefix}
libdir=${prefix}/lib
includedir=${prefix}/include
enginesdir=${libdir}/engines-3

Name: OpenSSL-libcrypto
Description: OpenSSL cryptography library
Version: 3.0.3
Libs: -L${libdir} -lcrypto
Libs.private: -lz -ldl -pthread 
Cflags: -I${includedir}
EOF,
    ];
    protected array $depNames = [
        'zlib' => true,
    ];

    protected function build(): void
    {
        Log::i("building {$this->name}");
        $ret = 0;
        $ex_lib = '-ldl -pthread';
        $env = $this->config->pkgconfEnv . ' ' .
            "CFLAGS='{$this->config->archCFlags}'";

        switch ($this->config->libc) {
            case CLib::MUSL_WRAPPER:
                $env .= " CC='{$this->config->cc} " .
                    '-static ' .
                    '-idirafter ' . realpath('include') . ' ' .
                    ($this->config->arch === php_uname('m') ? '-idirafter /usr/include/ ' : '') .
                    "-idirafter /usr/include/{$this->config->arch}-linux-gnu/'";
                break;
            case Clib::MUSL:    
                $ex_lib = '';
            case Clib::GLIBC:
                $env .= " CC='{$this->config->cc} " .
                    '-static ' .
                    '-static-libgcc ' .
                    '-idirafter ' . realpath('include') . ' ' .
                    ($this->config->arch === php_uname('m') ? '-idirafter /usr/include/ ' : '') . "' ";
                break;
            default:
                throw new Exception("unsupported libc: {$this->config->libc->name}");
        }

        $zlib = '';
        $libzlib = $this->config->getLib('zlib');
        //var_dump($libzlib);
        if ($libzlib) {
            Log::i("{$this->name} with zlib support");
            $ex_lib = $libzlib->getStaticLibFiles() . ' ' . $ex_lib;
            $zlib = "zlib";
        }

        $ex_lib = trim($ex_lib);

        $clangPostfix = Util::getCCType($this->config->cc) === 'clang' ? '-clang' : '';

        passthru(
            $this->config->setX . ' && ' .
                "cd {$this->sourceDir} && " .
                "$env ./Configure no-shared $zlib " .
                '--prefix=/ ' . //use prefix=/
                '--libdir=lib ' .
                '--static ' .
                '-static ' .
                " linux-{$this->config->arch}{$clangPostfix} && " .
                "make clean && " .
                "make -j{$this->config->concurrency} CNF_EX_LIBS=\"$ex_lib\" && " .
                'make install_sw DESTDIR=' . realpath('.'),
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to build {$this->name}");
        }
    }
}
