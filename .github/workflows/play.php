<?php

function arg2arr(string $arg): array
{
    return array_filter(array_map("trim", explode(',', $arg)));
}

$flavors = arg2arr(<<<'ARG'

ARG);
$archs = arg2arr(<<<'ARG'

ARG);
$sapis = arg2arr(<<<'ARG'

ARG);
$libcs = arg2arr(<<<'ARG'

ARG);
$phpVers = arg2arr(<<<'ARG'

ARG);

if (!$flavors) {
    $flavors = ['min', 'lite', 'max', 'max-swow', 'max-libev'];
}
if (!$archs) {
    $archs = ['x86_64', 'aarch64'];
}
if (!$sapis) {
    $sapis = ['micro', 'micro_cli', 'cli'];
}
if (!$libcs) {
    $libcs = ['musl_static', 'musl_shared', 'glibc_shared'];
}
if (!$phpVers) {
    $phpVers = ['8.0', '8.1', '8.2'];
}

$customExtensions = <<<'ARG'

ARG;
$customLibraries = <<<'ARG'

ARG;
$customExtensions = trim($customExtensions);
$customLibraries = trim($customLibraries);

foreach ($flavors as $flavor) {
    foreach ($archs as $arch) {
        foreach ($sapis as $sapi) {
            foreach ($libcs as $libc) {
                foreach ($phpVers as $phpVer) {
                    $libraries = match ($flavor) {
                        'min' => 'libffi',
                        'lite' => 'zstd,zlib,libffi,libzip,bzip2,xz,onig',
                        'max', 'max-swow', 'max-libev' => 'zstd,libssh2,curl,zlib,brotli,libffi,openssl,libzip,bzip2,nghttp2,onig,libyaml,xz,libxml2',
                        'max-swoole' => 'zstd,libssh2,curl,zlib,brotli,libffi,openssl,libzip,bzip2,nghttp2,onig,libyaml,xz,libxml2,libstdc++',
                        'custom' => $customLibraries,
                    };
                    $extensions = match ($flavor) {
                        'min' => 'posix,pcntl,ffi,filter,tokenizer,ctype',
                        'lite' => 'opcache,posix,pcntl,ffi,filter,tokenizer,ctype,iconv,mbstring,mbregex,sockets,zip,zstd,zlib,bz2,phar,fileinfo',
                        'max' => 'iconv,dom,xml,simplexml,xmlwriter,xmlreader,opcache,bcmath,pdo,phar,mysqlnd,mysqli,pdo,pdo_mysql,mbstring,mbregex,session,ctype,fileinfo,filter,tokenizer,curl,ffi,redis,sockets,openssl,zip,zlib,bz2,yaml,zstd,posix,pcntl,sysvshm,sysvsem,sysvmsg',
                        'max-swow' => 'iconv,dom,xml,simplexml,xmlwriter,xmlreader,opcache,bcmath,pdo,phar,mysqlnd,mysqli,pdo,pdo_mysql,mbstring,mbregex,session,ctype,fileinfo,filter,tokenizer,curl,ffi,redis,sockets,openssl,zip,zlib,bz2,yaml,zstd,posix,pcntl,sysvshm,sysvsem,sysvmsg,swow',
                        'max-swoole' => 'iconv,dom,xml,simplexml,xmlwriter,xmlreader,opcache,bcmath,pdo,phar,mysqlnd,mysqli,pdo,pdo_mysql,mbstring,mbregex,session,ctype,fileinfo,filter,tokenizer,curl,ffi,redis,sockets,openssl,zip,zlib,bz2,yaml,zstd,posix,pcntl,sysvshm,sysvsem,sysvmsg,swoole',
                        'max-libev' => 'iconv,dom,xml,simplexml,xmlwriter,xmlreader,opcache,bcmath,pdo,phar,mysqlnd,mysqli,pdo,pdo_mysql,mbstring,mbregex,session,ctype,fileinfo,filter,tokenizer,curl,ffi,redis,sockets,openssl,zip,zlib,bz2,yaml,zstd,posix,pcntl,sysvshm,sysvsem,sysvmsg,libev',
                        'custom' => $customExtensions,
                    };
                    $job = [
                        'flavor' => $flavor,
                        'libraries' => $libraries,
                        'extensions' => $extensions,
                        'arch' => $arch,
                        'sapi' => $sapi,
                        'libc' => $libc,
                        'phpVer' => $phpVer,
                    ];
                    $jobs[] = $job;
                }
            }
        }
    }
}

$json = json_encode($jobs);
file_put_contents(getenv('GITHUB_OUTPUT'), "jobs=$json");
