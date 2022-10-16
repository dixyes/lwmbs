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

trait UnixUtilTrait {
    public static function checkCCFlag(string $flag, string $cc):string {
        $ret = 0;
        exec("echo | $cc -E -x c - $flag", $dummy, $ret);
        if ($ret != 0) {
            return "";
        }
        return $flag;   
    }
    
    public static function checkCCFlags(array $flags, string $cc):array {
        return array_filter($flags, fn ($flag) => static::checkCCFlag($flag, $cc));
    }

    public static function libtoolCCFlags(array $flags):string {
        return implode(' ', array_map(fn($x)=> "-Xcompiler $x", $flags));
    }

    public static function makeCmakeToolchainFile(
        string $os,
        string $targetArch,
        string $cflags,
        ?string $cc=null,
        ?string $cxx=null
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
CMAKE;
        file_put_contents('./toolchain.cmake', $toolchain);
        return realpath('./toolchain.cmake');
    }

    public static function replaceConfigHeaderLine(string $line, string $replace = '', string $file = 'src/php-src/main/php_config.h') {
        $header = file_get_contents($file);
        $header = preg_replace('/^' . $line . '$/m', $replace, $header);
        file_put_contents($file, $header);
    }

    public static function patchPHPConfigure(Config $config) {
        $frameworks = PHP_OS_FAMILY === 'Darwin' ? ' ' . $config->getFrameworks(true) . ' ' : '';
        $curl = $config->getExt('curl');
        if ($curl) {
            Log::i('patching configure for curl checks');
            $configure = file_get_contents('src/php-src/configure');
            $configure = preg_replace(
                '/-lcurl/',
                $curl->getStaticLibFiles() . $frameworks,
                $configure
            );
            file_put_contents('src/php-src/configure', $configure);
        }
        $bzip2 = $config->getExt('bz2');
        if ($bzip2) {
            Log::i('patching configure for bzip2 checks');
            $configure = file_get_contents('src/php-src/configure');
            $configure = preg_replace(
                '/-lbz2/',
                $bzip2->getStaticLibFiles() . $frameworks,
                $configure
            );
            file_put_contents('src/php-src/configure', $configure);
        }
        Log::i('patching configure for disable capstone');
        $configure = file_get_contents('src/php-src/configure');
        $configure = preg_replace(
            '/have_capstone="yes"/',
            'have_capstone="no"',
            $configure
        );
        file_put_contents('src/php-src/configure', $configure);
        if (php_uname('m') !== $config->arch) {
            // cross-compiling
            switch ($config->arch) {
                case 'aarch64':
                case 'arm64':
                    // almost all bsd/linux supports this
                    Log::i('patching configure for shm mmap checks');
                    $configure = file_get_contents('src/php-src/configure');
                    $configure = preg_replace('/have_shm_mmap_anon=no/', 'have_shm_mmap_anon=yes', $configure);
                    $configure = preg_replace('/have_shm_mmap_posix=no/', 'have_shm_mmap_posix=yes', $configure);
                    file_put_contents('src/php-src/configure', $configure);
                    break;
                case 'x86_64':
                    break;
                default:
                    throw new Exception("unsupported arch: " . $config->arch);
            }
        }
    }
    public static function getCCType(string $cc):string {
        switch(true) {
            case str_ends_with($cc, 'c++'):
            case str_ends_with($cc, 'cc'):
            case str_ends_with($cc, 'g++'):
            case str_ends_with($cc, 'gcc'):
                $ccType = 'gcc';
                break;
            case $cc === 'clang++':
            case $cc === 'clang':
            case str_starts_with($cc, 'musl-clang'):
                $ccType = 'clang';
                break;
            default:
                throw new Exception("unknown cc type: $cc");
        }
        return $ccType;
    }
}