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

class Libxz extends Library
{
    use LinuxLibraryTrait;
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

    protected function build(): void
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
                'autoreconf -i --force && ' .
                "{$this->config->configureEnv} " . ' ./configure ' .
                '--enable-static ' .
                '--disable-shared ' .
                "--host={$this->config->arch}-unknown-linux " .
                '--disable-xz ' .
                '--disable-xzdec ' .
                '--disable-lzmadec ' .
                '--disable-lzmainfo ' .
                '--disable-scripts ' .
                '--disable-doc ' .
                "$libiconv " .
                '--prefix= && ' . //use prefix=/
                "make clean && " .
                "make -j{$this->config->concurrency} && " .
                'make install DESTDIR=' . realpath('.'),
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to build {$this->name}");
        }
    }
}
