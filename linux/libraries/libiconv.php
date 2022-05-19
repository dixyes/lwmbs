<?php

class Liblibiconv extends Library
{
    protected string $name = 'libiconv';
    protected array $staticLibs = [
        'libiconv.a',
        'libcharset.a',
    ];
    protected array $headers = [
        'iconv.h',
        'libcharset.h',
    ];
    protected array $pkgconfs = [
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
