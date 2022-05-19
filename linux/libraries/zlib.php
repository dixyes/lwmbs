<?php

class Libzlib extends Library
{
    protected string $name = 'zlib';
    protected array $staticLibs = [
        'libz.a',
    ];
    protected array $headers = [
        'zlib.h',
        'zconf.h',
    ];
    protected array $pkgconfs = [
        'zlib.pc' => <<<'EOF'
exec_prefix=${prefix}
libdir=${exec_prefix}/lib
includedir=${prefix}/include

Name: zlib
Description: zlib compression library
Version: 1.2.12

Requires:
Libs: -L${libdir} -L${sharedlibdir} -lz
Cflags: -I${includedir}
EOF,
    ];
    protected array $depNames = [
    ];

    use LinuxLibraryTrait;

    protected function build():void
    {
        Log::i("building {$this->name}");
        $ret = 0;
        passthru(
            $this->config->setX . ' && ' .
                "cd {$this->sourceDir} && " .
                "{$this->config->configureEnv} " . $this->config->libc->getCCEnv() . ' ./configure ' .
                '--static ' .
                "--archs=\"-arch {$this->config->arch}\" " .
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
