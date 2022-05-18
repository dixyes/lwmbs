<?php

class Libonig implements ILibrary
{
    private string $name = 'onig';
    private array $staticLibs = [
        'libonig.a',
    ];
    private array $headers = [
        'oniggnu.h',
        'oniguruma.h',
    ];
    private array $pkgconfs = [
        'oniguruma.pc' =><<<'EOF'
exec_prefix=${prefix}
libdir=${exec_prefix}/lib
includedir=${exec_prefix}/include
datarootdir=/usr/share
datadir=/usr/share

Name: oniguruma
Description: Regular expression library
Version: 6.9.8
Requires:
Libs: -L${libdir} -lonig
Cflags: -I${includedir}
EOF
    ];

    use Library;

    private function build()
    {
        Log::i("building {$this->name}");
        $ret = 0;
        passthru(
            $this->config->setX . ' && ' .
                "cd {$this->sourceDir} && " .
                "{$this->config->configureEnv} ". $this->config->libc->getCCEnv() . ' ./configure ' .
                '--enable-static ' .
                '--disable-shared ' .
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
