<?php

final class Util
{
    use CommonUtil;
    const NEEDED_COMMANDS = ['gcc', 'make', 'bison', 'flex', 'pkgconf', 'git', 'autoconf', 'automake', 'tar', 'unzip', 'xz', 'gzip', 'bzip2', 'cmake'];
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
            if ($distro['distro'] !== 'redhat' || !str_starts_with($distro['ver'], '6')) {
                Log::w("using glibc on {$distro['dist']} {$distro['ver']} may require target machines glibc version");
            }
            Log::i("using glibc");
            return CLib::GLIBC;
        }
    }

}
