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

class Libbrotli extends Library
{
    use LinuxLibraryTrait;
    protected string $name = 'brotli';
    protected array $staticLibs = [
        'libbrotlidec-static.a',
        'libbrotlienc-static.a',
        'libbrotlicommon-static.a',
    ];
    protected array $headers = [
        'brotli'
    ];
    protected array $pkgconfs = [
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
    protected array $depNames = [];

    protected function build(): void
    {
        throw new Exception("not implemented");
    }
}
