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
            $ccLine = 'set(CMAKE_C_COMPILER ' . realpath($cc) .')';
        }
        $cxxLine = '';
        if($cxx) {
            $cxxLine = 'set(CMAKE_CXX_COMPILER ' . realpath($cxx) .')';
        }
        $toolchain = <<<CMAKE
SET(CMAKE_SYSTEM_NAME $os)
SET(CMAKE_SYSTEM_PROCESSOR $targetArch)
$ccLine
SET(CMAKE_C_FLAGS "$cflags")
SET(CMAKE_CXX_FLAGS "$cflags")
SET(CMAKE_FIND_ROOT_PATH "$root")
CMAKE;
        file_put_contents('./toolchain.cmake', $toolchain);
        return realpath('./toolchain.cmake');
    }

    public static function replaceConfigHeaderLine(string $line, string $replace = '', string $file = 'src/php-src/main/php_config.h') {
        $header = file_get_contents('src/php-src/main/php_config.h');
        $header = preg_replace('/^'.$line.'$/m', $replace, $header);
        file_put_contents('src/php-src/main/php_config.h', $header);
    }

    public static function patchPHPConfigure(Config $config) {
        $curl = $config->getExt('curl');
        if ($curl) {
            Log::i('patching configure for curl checks');
            $configure = file_get_contents('src/php-src/configure');
            $configure = preg_replace('/-lcurl/', $curl->getStaticLibFiles(), $configure);
            file_put_contents('src/php-src/configure', $configure);
        }
        $bzip2 = $config->getExt('bz2');
        if ($bzip2) {
            Log::i('patching configure for bzip2 checks');
            $configure = file_get_contents('src/php-src/configure');
            $configure = preg_replace('/-lbz2/', $bzip2->getStaticLibFiles(), $configure);
            file_put_contents('src/php-src/configure', $configure);
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
                $ccType = 'clang';
                break;
            default:
                throw new Exception("unknown cc type: $cc");
        }
        return $ccType;
    }

    public static function getArchCFlags(string $cc, string $arch):string {
        return match(static::getCCType($cc)) {
            'clang' => match ($arch) {
                'x86_64' => '--target=x86_64-unknown-linux',
                'arm64','aarch64' => '--target=arm64-unknown-linux',
                default => throw new Exception('unsupported arch: ' . $arch),
            },
            'gcc' => '',
        };
    }
}