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
    use LinuxLibraryTrait;
    protected string $name = 'libffi';
    protected array $staticLibs = [
        'libffi.a',
    ];
    protected array $headers = [
        'ffi.h',
        'ffitarget.h',
    ];
    protected array $pkgconfs = [
        'libffi.pc' => <<<'EOF'
exec_prefix=${prefix}
libdir=${exec_prefix}/lib
toolexeclibdir=${libdir}
includedir=${prefix}/include

Name: libffi
Description: Library supporting Foreign Function Interfaces
Version: 3.4.4
Libs: -L${toolexeclibdir} -lffi
Cflags: -I${includedir}
EOF
    ];
    protected array $depNames = [];

    protected function build(): void
    {
        Log::i("building {$this->name}");
        $ret = 0;
        $env = $this->config->pkgconfEnv . ' ' .
            " CFLAGS='{$this->config->cFlags}'";

        switch ($this->config->libc) {
            case CLib::MUSL_WRAPPER:
                $env .= " CC='{$this->config->cc} " .
                    '-static ' .
                    '-idirafter ' . realpath('include') . ' ' .
                    ($this->config->arch === php_uname('m') ? '-idirafter /usr/include/ ' : '') .
                    "-idirafter /usr/include/{$this->config->arch}-linux-gnu/'";
                break;
            case CLib::MUSL:
            case CLib::GLIBC:
                $env .= " CC='{$this->config->cc}'";
                break;
            default:
                throw new Exception("unsupported libc: {$this->config->libc->name}");
        }

        // https://github.com/libffi/libffi/pull/764
        $patch = <<<'PATCH'
        From cbfb9b436ab13e4b4aba867d061e11d7f89a351c Mon Sep 17 00:00:00 2001
        From: serge-sans-paille <sguelton@mozilla.com>
        Date: Wed, 1 Feb 2023 18:09:25 +0100
        Subject: [PATCH] Forward declare open_temp_exec_file

        It's defined in closures.c and used in tramp.c.
        Also declare it as an hidden symbol, as it should be.
        ---
         include/ffi_common.h | 4 ++++
         src/tramp.c          | 4 ++++
         2 files changed, 8 insertions(+)

        diff --git a/include/ffi_common.h b/include/ffi_common.h
        index 2bd31b03d..c53a79493 100644
        --- a/include/ffi_common.h
        +++ b/include/ffi_common.h
        @@ -128,6 +128,10 @@ void *ffi_data_to_code_pointer (void *data) FFI_HIDDEN;
            static trampoline. */
         int ffi_tramp_is_present (void *closure) FFI_HIDDEN;

        +/* Return a file descriptor of a temporary zero-sized file in a
        +   writable and executable filesystem. */
        +int open_temp_exec_file(void) FFI_HIDDEN;
        +
         /* Extended cif, used in callback from assembly routine */
         typedef struct
         {
        diff --git a/src/tramp.c b/src/tramp.c
        index b9d273a1a..c3f4c9933 100644
        --- a/src/tramp.c
        +++ b/src/tramp.c
        @@ -39,6 +39,10 @@
         #ifdef __linux__
         #define _GNU_SOURCE 1
         #endif
        +
        +#include <ffi.h>
        +#include <ffi_common.h>
        +
         #include <stdio.h>
         #include <unistd.h>
         #include <stdlib.h>
        PATCH;
        file_put_contents("{$this->sourceDir}/764.patch", $patch);
        passthru(
            "cd {$this->sourceDir} && " .
                "patch -p1 < 764.patch",
            $ret
        );


        passthru(
            $this->config->setX . ' && ' .
                "cd {$this->sourceDir} && " .
                "$env ./configure " .
                '--enable-static ' .
                '--disable-shared ' .
                "--host={$this->config->arch}-unknown-linux " .
                "--target={$this->config->arch}-unknown-linux " .
                '--prefix= ' . //use prefix=/
                '--libdir=/lib && ' .
                "make clean && " .
                "make -j{$this->config->concurrency} && " .
                'make install DESTDIR=' . realpath('.'),
            $ret
        );
        if (is_file('lib64/libffi.a')){
            copy('lib64/libffi.a', 'lib/libffi.a');
            unlink('lib64/libffi.a');
        }
        if ($ret !== 0) {
            throw new Exception("failed to build {$this->name}");
        }
        $this->makeFakePkgconfs();
    }
}
