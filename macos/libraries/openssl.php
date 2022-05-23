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

class Libopenssl extends Library
{
    use MacOSLibraryTrait;
    protected string $name = 'openssl';
    protected array $staticLibs = [
        'libssl.a',
        'libcrypto.a',
    ];
    protected array $headers = [
        'openssl',
    ];
    protected array $depNames = [
        'zlib' => true,
    ];

    protected function build(): void
    {
        Log::i("building {$this->name}");
        $ret = 0;
        $ex_lib = '';
        $env = $this->config->configureEnv;

        $zlib = '';
        $libzlib = $this->config->getLib('zlib');
        //var_dump($libzlib);
        if ($libzlib) {
            Log::i("{$this->name} with zlib support");
            $ex_lib = $libzlib->getStaticLibFiles() . ' ' . $ex_lib;
            $zlib = "zlib";
        }

        $ex_lib = trim($ex_lib);
        passthru(
            $this->config->setX . ' && ' .
                "cd {$this->sourceDir} && " .
                "$env ./Configure no-shared $zlib " .
                '--prefix=/ ' . //use prefix=/
                '--libdir=/lib ' .
                " darwin64-{$this->config->arch}-cc && " .
                "make clean && " .
                "make -j{$this->config->concurrency} CNF_EX_LIBS=\"$ex_lib\" && " .
                'make install_sw DESTDIR=' . realpath('.'),
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to build {$this->name}");
        }
    }
}
