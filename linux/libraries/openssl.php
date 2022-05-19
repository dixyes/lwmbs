<?php

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
        $ex_lib = '-pthread -dl';
        $zlib = '';
        $libzlib = $this->config->getLib('zlib');
        //var_dump($libzlib);
        if ($libzlib) {
            Log::i("{$this->name} with zlib support");
            $ex_lib .= ' ' . $libzlib->getStaticLibFiles();
            $zlib = "zlib";
        }
        $env = $this->config->configureEnv;
        switch ($this->config->libc) {
            case CLib::MUSL_WRAPPER:
                $env .= ' CC="' .
                    $this->config->libc->getCC() . ' ' .
                    '-static ' .
                    '-idirafter ' . realpath('include') . ' ' .
                    '-idirafter /usr/include/ ' .
                    '-idirafter /usr/include/x86_64-linux-gnu/"';
                break;
            case Clib::GLIBC:
                break;
            default:
                throw new Exception("unsupported libc: {$this->config->libc->name}");
        }
        passthru(
            $this->config->setX . ' && ' .
                "cd {$this->sourceDir} && " .
                "$env ./Configure no-shared $zlib " .
                '--prefix=/ ' .
                '--libdir=/lib && ' . //use prefix=/
                "make -j{$this->config->concurrency} build_sw CNF_EX_LIBS=\"$ex_lib\" && " .
                'make install_sw DESTDIR=' . realpath('.'),
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to build {$this->name}");
        }
    }
}
