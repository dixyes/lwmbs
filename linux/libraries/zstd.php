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

class Libzstd extends Library
{
    use LinuxLibraryTrait;
    protected string $name = 'zstd';
    protected array $staticLibs = [
        'libzstd.a',
    ];
    protected array $headers = [
        'zdict.h',
        'zstd.h',
        'zstd_errors.h',
    ];
    protected array $pkgconfs = [
        'libzstd.pc' => <<<'EOF'
exec_prefix=${prefix}
includedir=${prefix}/include
libdir=${exec_prefix}/lib

Name: zstd
Description: fast lossless compression algorithm library
URL: http://www.zstd.net/
Version: 1.5.2
Libs: -L${libdir} -lzstd
Libs.private: -pthread
Cflags: -I${includedir}
EOF,
    ];
    protected array $depNames = [
    ];

    protected function build(): void
    {
        Log::i("building {$this->name}");
        $ret = 0;
        passthru(
            $this->config->setX . ' && ' .
            "cd {$this->sourceDir} && " .
            "make {$this->config->configureEnv} PREFIX='".realpath('.')."' clean" . ' && ' .
            "make -j{$this->config->concurrency} " .
                "{$this->config->configureEnv} " .
                "PREFIX='".realpath('.')."' ".
                '-C lib libzstd.a CPPFLAGS_STATLIB=-DZSTD_MULTITHREAD && ' .
            'cp lib/libzstd.a '.realpath('./lib').'  && '.
            'cp lib/zdict.h  lib/zstd_errors.h  lib/zstd.h '.realpath('./include'),
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to build {$this->name}");
        }
        $this->makeFakePkgconfs();
    }
}
