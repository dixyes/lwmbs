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

final class Util
{
    use CommonUtilTrait;
    public static function getCpuCount(): int
    {
        return (int)shell_exec('echo %NUMBER_OF_PROCESSORS%');
    }

    public static function makeCmakeToolchainFile(
        string $targetArch,
        string $cflags = '/MT /O1 /Ob1 /DNDEBUG /D_ACRTIMP= /D_CRTIMP=',
        string $ldflags = '/nodefaultlib:msvcrt /nodefaultlib:msvcrtd /defaultlib:libcmt',
    ):string {
        Log::i("making cmake tool chain file for $targetArch with CFLAGS='$cflags'");
        $root = str_replace('\\', '\\\\', realpath('deps'));
        $toolchain = <<<CMAKE
SET(CMAKE_SYSTEM_PROCESSOR $targetArch)
SET(CMAKE_C_FLAGS "$cflags")
SET(CMAKE_C_FLAGS_DEBUG "$cflags")
SET(CMAKE_CXX_FLAGS "$cflags")
SET(CMAKE_CXX_FLAGS_DEBUG "$cflags")
SET(CMAKE_EXE_LINKER_FLAGS "$ldflags")
SET(CMAKE_FIND_ROOT_PATH "$root")
SET(CMAKE_MSVC_RUNTIME_LIBRARY MultiThreaded)
CMAKE;
        file_put_contents('./toolchain.cmake', $toolchain);
        return realpath('./toolchain.cmake');
    }
}
