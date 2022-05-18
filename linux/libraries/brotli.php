<?php

class Libbrotli implements ILibrary
{
    private string $name = 'brotli';
    private array $staticLibs = [
        'libbrotlidec-static.a',
        'libbrotlienc-static.a',
        'libbrotlicommon-static.a',
    ];
    private array $headers = [
        'brotli'
    ];
    private array $pkgconfs = [
        'libbrotlicommon.pc' => <<<'EOF'
exec_prefix=${prefix}
libdir=${prefix}/lib
includedir=${prefix}/include

Name: libbrotlicommon
URL: https://github.com/google/brotli
Description: Brotli common dictionary library
Version: 1.0.9
Libs: -L${libdir} -lbrotlicommon
Cflags: -I${includedir}
EOF,
        'libbrotlienc.pc' => <<<'EOF'
exec_prefix=${prefix}
libdir=${prefix}/lib
includedir=${prefix}/include

Name: libbrotlienc
URL: https://github.com/google/brotli
Description: Brotli encoder library
Version: 1.0.9
Libs: -L${libdir} -lbrotlienc
Requires.private: libbrotlicommon >= 1.0.2
Cflags: -I${includedir}
EOF,
        'libbrotlidec.pc' => <<<'EOF'
exec_prefix=${prefix}
libdir=${prefix}/lib
includedir=${prefix}/include

Name: libbrotlidec
URL: https://github.com/google/brotli
Description: Brotli decoder library
Version: 1.0.9
Libs: -L${libdir} -lbrotlidec
Requires.private: libbrotlicommon >= 1.0.2
Cflags: -I${includedir}
EOF,
    ];

    use Library;

    private function build()
    {
        throw new Exception("not implemented");
    }
}
