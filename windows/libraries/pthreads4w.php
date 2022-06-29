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

class Libpthreads4w extends Library
{
    use WindowsLibraryTrait;
    protected string $name = 'pthreads4w';
    protected array $staticLibs = [
        'libpthreadVC3.lib',
    ];
    protected array $headers = [
        '_ptw32.h',
        'pthread.h',
        'sched.h',
        'semaphore.h',
    ];
    protected array $depNames = [
    ];

    protected function build(): void
    {
        Log::i("building {$this->name}");
        
        file_put_contents('src/pthreads4w/nmake_wrapper.bat',
            'nmake /E /nologo /f Makefile '.
            'DESTROOT=../../deps '.
            'XCFLAGS="/MT /Z7" '.
            'EHFLAGS="/I. /DHAVE_CONFIG_H /W3 /O2 /Ob2 /D__PTW32_STATIC_LIB /D__PTW32_BUILD_INLINED" '.
            'CLEANUP=__PTW32_CLEANUP_C '.
            '%*');

        $ret = 0;
        passthru(
            "cd {$this->sourceDir} && " .
            "{$this->config->phpBinarySDKCmd} -t nmake_wrapper.bat --task-args clean && ".
            "{$this->config->phpBinarySDKCmd} -t nmake_wrapper.bat --task-args pthreadVC3.inlined_static_stamp ",
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to build {$this->name}");
        }

        copy('src/pthreads4w/libpthreadVC3.lib', 'deps/lib/libpthreadVC3.lib');
        copy('src/pthreads4w/_ptw32.h', 'deps/include/_ptw32.h');
        copy('src/pthreads4w/pthread.h', 'deps/include/pthread.h');
        copy('src/pthreads4w/sched.h', 'deps/include/sched.h');
        copy('src/pthreads4w/semaphore.h', 'deps/include/semaphore.h');
    }
}
