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
    public static function findStaticLib(string $name): ?array
    {
        $paths = getenv('LIBPATH');
        if (!$paths) {
            $paths = "/lib:/lib64:/usr/lib:/usr/lib64:/usr/local/lib:/usr/local/lib64";
        }
        foreach (explode(':', $paths) as $path) {
            if (file_exists("$path/$name")) {
                return ["$path", "$name"];
            }
        }
        return null;
    }
    public static function findStaticLibs(array $names): ?array
    {
        $ret = [];
        foreach ($names as $name) {
            $path = static::findStaticLib($name);
            if (!$path) {
                Log::w("static library $name not found");
                return null;
            }
            $ret[] = $path;
        }
        return $ret;
    }

    public static function findHeader(string $name): ?array
    {
        $paths = getenv('INCLUDEPATH');
        if (!$paths) {
            $paths = "/include:/usr/include:/usr/local/include";
        }
        foreach (explode(':', $paths) as $path) {
            if (file_exists("$path/$name") || is_dir("$path/$name")) {
                return ["$path", "$name"];
            }
        }
        return null;
    }

    public static function findHeaders(array $names): ?array
    {
        $ret = [];
        foreach ($names as $name) {
            $path = static::findHeader($name);
            if (!$path) {
                Log::w("header $name not found");
                return null;
            }
            $ret[] = $path;
        }
        return $ret;
    }

    public static function getCpuCount(): int
    {
        $ncpu = 1;

        if (is_file('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            preg_match_all('/^processor/m', $cpuinfo, $matches);
            $ncpu = count($matches[0]);
        }

        return $ncpu;
    }

    private static function getOSRelease(): array
    {
        $ret = [
            'dist' => 'unknown',
            'ver' => 'unknown',
        ];
        switch (true) {

            case file_exists('/etc/os-release'):
                $lines = file('/etc/os-release');
                foreach ($lines as $line) {
                    if (preg_match('/^ID=(.*)$/', $line, $matches)) {
                        $ret['dist'] = $matches[1];
                    }
                    if (preg_match('/^VERSION_ID=(.*)$/', $line, $matches)) {
                        $ret['ver'] = $matches[1];
                    }
                }
                $ret['dist'] = trim($ret['dist'], '"\'');
                $ret['ver'] = trim($ret['ver'], '"\'');
                if (0 === strcasecmp($ret['dist'], 'centos')) {
                    $ret['dist'] = 'redhat';
                }
                break;
            case file_exists('/etc/centos-release'):
                $lines = file('/etc/centos-release');
                goto rh;
            case file_exists('/etc/redhat-release'):
                $lines = file('/etc/redhat-release');
                rh:
                foreach ($lines as $line) {
                    if (preg_match('/release\s+(\d+(\.\d+)*)/', $line, $matches)) {
                        $ret['dist'] = 'redhat';
                        $ret['ver'] = $matches[1];
                    }
                }
                break;
        }
        return $ret;
    }

    public static function fixPkgConfig(string $path)
    {
        Log::i("fixing pc $path");

        $workspace = realpath('.');
        if ($workspace === '/') {
            $workspace = '';
        }

        $content = file_get_contents($path);
        $content = preg_replace('/^prefix=.+$/m', "prefix=$workspace", $content);
        $content = preg_replace('/^libdir=.+$/m', 'libdir=${prefix}/lib', $content);
        $content = preg_replace('/^includedir=.+$/m', 'includedir=${prefix}/include', $content);
        file_put_contents($path, $content);
    }

    public static function fixPkgConfigs(array $paths)
    {
        foreach ($paths as $path) {
            static::fixPkgConfig($path);
        }
    }

    public static function chooseLibc(string $cc): Clib
    {
        Log::i('checking libc');
        $self = file_get_contents('/proc/self/exe', length: 4096);
        preg_match('/' . CLib::MUSL->getLDInterpreter() . '/', $self, $matches);
        if ($matches) {
            // if we are musl, use native musl
            Log::i("using native musl");
            return CLib::MUSL;
        }

        // else try to use musl cc wrapper
        if ($cc === 'musl-gcc' || $cc === 'musl-clang') {
            Log::i("using musl wrapper");
            if ($cc === 'musl-clang') {
                Log::w("musl-clang is buggy");
            }
            return CLib::MUSL_WRAPPER;
        } else {
            $distro = static::getOSRelease();
            if ($distro['dist'] !== 'redhat' || !str_starts_with($distro['ver'], '7')) {
                Log::w("using glibc on {$distro['dist']} {$distro['ver']} may require target machines glibc version");
            }
            Log::i("using glibc");
            return CLib::GLIBC;
        }
    }

    public static function chooseCC(): string
    {
        Log::i('checking cc');
        if (Util::findCommand('clang')) {
            Log::i("using clang");
            return 'clang';
        } else if (Util::findCommand('gcc')) {
            Log::i("using gcc");
            return 'gcc';
        }
        throw new Exception("no supported cc found");
    }

    public static function chooseCXX(): string
    {
        Log::i('checking cxx');
        if (Util::findCommand('clang++')) {
            Log::i("using clang++");
            return 'clang++';
        } else if (Util::findCommand('g++')) {
            Log::i("using g++");
            return 'g++';
        }
        return static::chooseCC();
    }

    public static function patchConfigure(Config $config): void
    {

        passthru(
            $config->setX . ' && ' .
                'cd src/php-src && ' .
                './buildconf --force',
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to configure micro");
        }

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

    public static function replaceConfigHeaderLine(string $line, string $replace = ''): void
    {
        $header = file_get_contents('src/php-src/main/php_config.h');
        $header = preg_replace('/^' . $line . '$/m', $replace, $header);
        file_put_contents('src/php-src/main/php_config.h', $header);
    }

    public static function patchConfigHeader(Config $config): void
    {
        switch ($config->libc) {
            case CLib::MUSL_WRAPPER:
                // bad checks
                static::replaceConfigHeaderLine('#define HAVE_STRLCPY 1');
                static::replaceConfigHeaderLine('#define HAVE_STRLCAT 1');
            case CLib::MUSL:
                static::replaceConfigHeaderLine('#define HAVE_FUNC_ATTRIBUTE_IFUNC 1');
                break;
            case CLib::GLIBC:
                // avoid lcrypt dependency
                static::replaceConfigHeaderLine('#define HAVE_CRYPT 1');
                static::replaceConfigHeaderLine('#define HAVE_CRYPT_R 1');
                static::replaceConfigHeaderLine('#define HAVE_CRYPT_H 1');
                break;
            default:
                throw new Exception('not implemented');
        }
    }

    public static function genExtraLibs(Config $config): string
    {
        if ($config->libc === CLib::GLIBC) {
            $glibcLibs = [
                'rt',
                'm',
                'c',
                'pthread',
                'dl',
                'nsl',
                'anl',
                //'crypt',
                'resolv',
                'util',
            ];
            $makefile = file_get_contents('src/php-src/Makefile');
            preg_match('/^EXTRA_LIBS\s*=\s*(.*)$/m', $makefile, $matches);
            if (!$matches) {
                throw new Exception("failed to find EXTRA_LIBS in Makefile");
            }
            $_extra_libs = [];
            foreach (array_filter(explode(' ', $matches[1])) as $used) {
                foreach ($glibcLibs as $libName) {
                    if ("-l$libName" === $used && !in_array("-l$libName", $_extra_libs, true)) {
                        array_unshift($_extra_libs, "-l$libName");
                    }
                }
            }
            return ' ' . implode(' ', $_extra_libs);
        }
        return '';
    }

    public static function getTuneCFlags(string $arch): array
    {
        return match ($arch) {
            'x86_64' => [
                '-march=corei7',
                '-mtune=core-avx2',
            ],
            'arm64', 'aarch64' => [],
            default => throw new Exception('unsupported arch: ' . $arch),
        };
    }

    public static function getCrossCompilePrefix(string $cc, string $arch,): string
    {
        return match (static::getCCType($cc)) {
            // guessing clang toolchains
            'clang' => match ($arch) {
                'x86_64' => 'x86_64-linux-gnu-',
                'arm64', 'aarch64' => 'aarch64-linux-gnu-',
                default => throw new Exception('unsupported arch: ' . $arch),
            },
            // remove gcc postfix
            'gcc' => str_replace('-cc', '', str_replace('-gcc', '', $cc)) . '-',
        };
    }

    public static function getArchCFlags(string $cc, string $arch): string
    {
        if (php_uname('m') === $arch) {
            return '';
        }
        return match (static::getCCType($cc)) {
            'clang' => match ($arch) {
                'x86_64' => '--target=x86_64-unknown-linux',
                'arm64', 'aarch64' => '--target=arm64-unknown-linux',
                default => throw new Exception('unsupported arch: ' . $arch),
            },
            'gcc' => '',
        };
    }
}
