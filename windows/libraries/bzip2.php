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
    use WindowsLibraryTrait;
    protected string $name = 'bzip2';
    protected array $staticLibs = [
        ['libbz2.lib', 'libbz2_a.lib'],
    ];
    protected array $headers = [
        'bzlib.h',
    ];
    protected array $depNames = [
    ];

    protected function build(): void
    {
        Log::i("building {$this->name}");
        
        file_put_contents('src/bzip2/nmake_wrapper.bat', 'nmake /nologo /f Makefile.msc CFLAGS="-DWIN32 -MT -Ox -D_FILE_OFFSET_BITS=64 -nologo" %*');

        $ret = 0;
        passthru(
            "cd {$this->sourceDir} && " .
            "{$this->config->phpBinarySDKCmd} -t nmake_wrapper.bat --task-args clean && " .
            "{$this->config->phpBinarySDKCmd} -t nmake_wrapper.bat --task-args lib"
            ,
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to build {$this->name}");
        }

        copy('src/bzip2/libbz2.lib', 'deps/lib/libbz2.lib');
        copy('src/bzip2/libbz2.lib', 'deps/lib/libbz2_a.lib');
        copy('src/bzip2/bzlib.h', 'deps/include/bzlib.h');
    }
}
