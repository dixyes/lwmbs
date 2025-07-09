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
    use UnixUtilTrait;

    public static function getCpuCount(): int
    {
        exec('sysctl -n hw.ncpu', $output, $ret);
        if ($ret !== 0) {
            throw new Exception('Failed to get cpu count');
        }

        return (int) $output[0];
    }

    public static function getArchCFlags(string $arch):string {
        return match ($arch) {
            'x86_64' => '--target=x86_64-apple-darwin',
            'arm64','aarch64' => '--target=arm64-apple-darwin',
            default => throw new Exception('unsupported arch: ' . $arch),
        };
    }
    public static function makeCmakeToolchainFile(
        string $os,
        string $targetArch,
        string $cflags,
        ?string $cc=null,
        ?string $cxx=null,
        ?string $sdkRoot=null,
    ):string {
        Log::i("making cmake tool chain file for $os $targetArch with CFLAGS='$cflags'");
        $root = realpath('.');
        $ccLine = '';
        if($cc) {
            $ccLine = 'SET(CMAKE_C_COMPILER ' . Util::findCommand($cc) . ')';
        }
        $cxxLine = '';
        if($cxx) {
            $cxxLine = 'SET(CMAKE_CXX_COMPILER ' . Util::findCommand($cxx) . ')';
        }
        $toolchain = <<<CMAKE
SET(CMAKE_SYSTEM_NAME $os)
SET(CMAKE_SYSTEM_PROCESSOR $targetArch)
$ccLine
$cxxLine
SET(CMAKE_C_FLAGS "$cflags")
SET(CMAKE_CXX_FLAGS "$cflags")
SET(CMAKE_FIND_ROOT_PATH "$root")
SET(CMAKE_THREAD_LIBS_INIT "-lpthread")
CMAKE;
        if ($sdkRoot) {
            $toolchain .= "\nSET(CMAKE_OSX_SYSROOT \"$sdkRoot\")\n";
        }
        file_put_contents('./toolchain.cmake', $toolchain);
        return realpath('./toolchain.cmake');
    }
}
