<?php

class Libbzip2 implements ILibrary
{
    private string $name = 'bzip2';
    private array $staticLibs = [
        'libbz2.a',
    ];
    private array $headers = [
        'bzlib.h',
    ];
    private array $pkgconfs = [
        'bzip2.pc' => <<<'EOF'
exec_prefix=${prefix}
bindir=${exec_prefix}/bin
libdir=${exec_prefix}/lib
includedir=${prefix}/include

Name: bzip2
Description: A file compression library
Version: 1.0.8
Libs: -L${libdir} -lbz2
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
                "{$this->config->configureEnv} make -j{$this->config->concurrency} && " .
                'make install PREFIX=' . realpath('.'),
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to build {$this->name}");
        }
        $this->makeFakePkgconfs();
    }
}
