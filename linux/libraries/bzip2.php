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

class Libbzip2 extends Library
{
    use LinuxLibraryTrait;
    protected string $name = 'bzip2';
    protected array $staticLibs = [
        'libbz2.a',
    ];
    protected array $headers = [
        'bzlib.h',
    ];
    protected array $pkgconfs = [
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
    protected array $depNames = [];

    protected function build(): void
    {
        Log::i("building {$this->name}");
        $ret = 0;
        passthru(
            $this->config->setX . ' && ' .
                "cd {$this->sourceDir} && " .
                "make {$this->config->configureEnv} PREFIX='".realpath('.')."' clean" . ' && ' .
                "make -j{$this->config->concurrency} {$this->config->configureEnv} PREFIX='".realpath('.')."' libbz2.a" . ' && ' .
                // make install may fail when cross-compiling, so we copy files.
                'cp libbz2.a '.realpath('./lib').'  && '.
                'cp bzlib.h '.realpath('./include'),
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to build {$this->name}");
        }
        $this->makeFakePkgconfs();
    }
}
