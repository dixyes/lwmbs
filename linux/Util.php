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
    use CommonUtil;
    public const NEEDED_COMMANDS = ['gcc', 'make', 'bison', 'flex', 'pkg-config', 'git', 'autoconf', 'automake', 'tar', 'unzip', 'xz', 'gzip', 'bzip2', 'cmake'];
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
        $content = file_get_contents($path);
        $content = preg_replace('/^prefix=.+$/m', 'prefix=' . realpath('.'), $content);
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

    public static function chooseLibc()
    {
        Log::i('checking libc');
        $self = file_get_contents('/proc/self/exe', length: 4096);
        preg_match('/' . CLib::MUSL->getLDInterpreter() . '/', $self, $matches);
        if ($matches) {
            // if we are musl, use native musl
            Log::i("using native musl");
            throw new Exception("unsupported libc");
            return CLib::MUSL;
        }

        // else try to use musl-gcc wrapper
        if (static::findCommand('musl-gcc')) {
            Log::i("using musl wrapper");
            return CLib::MUSL_WRAPPER;
        } else {
            $distro = static::getOSRelease();
            if ($distro['dist'] !== 'redhat' || !str_starts_with($distro['ver'], '6')) {
                Log::w("using glibc on {$distro['dist']} {$distro['ver']} may require target machines glibc version");
            }
            Log::i("using glibc");
            return CLib::GLIBC;
        }
    }

    public static function patchConfigure(Config $config) {

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
        $bzip2 = $config->getExt('bzip2');
        if ($bzip2) {
            Log::i('patching configure for bzip2 checks');
            $configure = file_get_contents('src/php-src/configure');
            $configure = preg_replace('/-lbz2/', $bzip2->getStaticLibFiles(), $configure);
            file_put_contents('src/php-src/configure', $configure);
        }

    }
    public static function genExtraLibs(Config $config) {
        if ($config->libc === CLib::GLIBC) {
            $glibcLibs = [
                'rt',
                'm',
                'c',
                'pthread',
                'dl',
                'nsl',
                'anl',
                'crypt',
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
    }
}
