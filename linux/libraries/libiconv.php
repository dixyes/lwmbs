<?php

class Liblibiconv implements ILibrary
{
    private string $name = 'libiconv';
    private array $staticLibs = [
        'libiconv.a',
        'libcharset.a',
    ];
    private array $headers = [
        'iconv.h',
        'libcharset.h',
    ];
    private array $pkgconfs = [
    ];

    use Library;

    private function build()
    {
        Log::i("building {$this->name}");
        $ret = 0;
        passthru(
            $this->config->setX . ' && ' .
                "cd {$this->sourceDir} && " .
                "{$this->config->configureEnv} " . $this->config->libc->getCCEnv() . ' ./configure ' .
                '--enable-static '.
                '--disable-shared ' .
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
