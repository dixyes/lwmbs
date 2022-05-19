<?php

class Libxz extends Library
{
    protected string $name = 'xz';
    protected array $staticLibs = [
        'liblzma.a',
    ];
    protected array $headers = [
        'lzma',
    ];
    protected array $pkgconfs = [
        'liblzma.pc' => <<<'EOF'
exec_prefix=${prefix}
libdir=${exec_prefix}/lib
includedir=${exec_prefix}/include

Name: liblzma
Description: General purpose data compression library
URL: https://tukaani.org/xz/
Version: 5.2.5
Cflags: -I${includedir}
Libs: -L${libdir} -llzma
Libs.private: -pthread -lpthread
EOF,
    ];
    protected array $depNames = [
        'libiconv' => true,
    ];

    use LinuxLibraryTrait;

    protected function build():void
    {
        Log::i("building {$this->name}");
        $ret = 0;
        $libiconv = '';
        if ($this->config->getLib('libiconv')) {
            Log::i("{$this->name} with libiconv support");
            $libiconv = '--with-libiconv-prefix=' . realpath('.');
        }
        passthru(
            $this->config->setX . ' && ' .
                "cd {$this->sourceDir} && " .
                "{$this->config->configureEnv} ". $this->config->libc->getCCEnv() . ' ./configure ' .
                '--enable-static ' .
                '--disable-shared ' .
                '--disable-xz ' .
                '--disable-xzdec ' .
                '--disable-lzmadec ' .
                '--disable-lzmainfo ' .
                '--disable-scripts ' .
                '--disable-doc ' .
                "$libiconv " .
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
