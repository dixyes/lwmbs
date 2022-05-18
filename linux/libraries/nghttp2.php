<?php

class Libnghttp2 implements ILibrary
{
    private string $name = 'nghttp2';
    private array $staticLibs = [
        'libnghttp2.a',
    ];
    private array $headers = [
        'nghttp2',
    ];
    private array $pkgconfs = [
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

    use Library;

    private function build()
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
                "{$this->config->configureEnv} " . $this->config->libc->getCCEnv() . ' ./configure ' .
                '--enable-static '.
                '--disable-shared ' .
                '--enable-lib-only ' .
                '--with-boost=no ' .
                $args . ' ' .
                '--prefix= && ' . //use prefix=/
                "make -j{$this->config->concurrency} && " .
                'make install DESTDIR=' . realpath('.'),
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to build {$this->name}");
        }
    }
}
