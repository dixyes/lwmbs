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

class Liblibffi extends Library
{
    use MacOSLibraryTrait;
    protected string $name = 'libffi';
    protected array $staticLibs = [
        'libffi.a',
    ];
    protected array $headers = [
        'ffi.h',
        'ffitarget.h',
    ];
    protected array $depNames = [];

    protected function build(): void
    {
        Log::i("building {$this->name}");
        $ret = 0;
        passthru(
            $this->config->setX . ' && ' .
                "cd {$this->sourceDir} && " .
                "{$this->config->configureEnv} ./configure " .
                '--enable-static ' .
                '--disable-shared ' .
                "--host={$this->config->arch}-apple-darwin " .
                "--target={$this->config->arch}-apple-darwin " .
                '--prefix= ' . //use prefix=/
                '--libdir=/lib && ' .
                // force ptrauth for arm64
                ($this->config->arch === 'arm64' ?
                    'echo "\n#ifndef HAVE_ARM64E_PTRAUTH\n#define HAVE_ARM64E_PTRAUTH 1\n#endif\n" >> aarch64-apple-darwin/fficonfig.h &&' : '').
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
