#!php
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

require __DIR__ . '/autoload.php';

function mian($argv): int
{
    Util::setErrorHandler();

    $cmdArgs = Util::parseArgs($argv);
    foreach (array_keys($cmdArgs) as $k) {
        if (!in_array($k, [
            'all-static',
            'cc',
            'cxx',
            'arch',
        ], true)) {
            Log::e("Unknown argument: $k");
            Log::w("Usage: {$argv[0]} [--all-static] [--cc=<compiler>] [--cxx=<compiler>] [--arch=<arch>]");
            exit(1);
        }
    }

    $allStatic = (bool)($cmdArgs['all-static'] ?? false);

    $config = new Config(
        cc: $cmdArgs['cc'] ?? null,
        cxx: $cmdArgs['cxx'] ?? null,
        arch: $cmdArgs['arch'] ?? null,
    );

    $libNames = [
        'libssh2',
        'curl',
        'zlib',
        'brotli',
        'libiconv',
        'libffi',
        'openssl',
        'libzip',
        'bzip2',
        'nghttp2',
        'onig',
        'xz',
    ];

    $extNames = [
        'iconv',
        'bcmath',
        'pdo',
        'phar',
        'mysqli',
        'pdo',
        'pdo_mysql',
        'mbstring',
        'mbregex',
        'session',
        'pcntl',
        'posix',
        'ctype',
        'fileinfo',
        'filter',
        'tokenizer',
        'curl',
        'ffi',
        'swow',
        'redis',
        'parallel',
        'sockets',
        'openssl',
        'zlib',
        'bz2',
    ];

    if ($allStatic) {
        unset($libNames[array_search('libffi', $libNames, true)]);
        unset($extNames[array_search('ffi', $extNames, true)]);
    }

    foreach ($libNames as $name) {
        $lib = new ("Lib$name")($config);
        $config->addLib($lib);
    }
    //var_dump(array_map(fn($x)=>$x->getName(),$config->makeLibArray()));

    foreach ($config->makeLibArray() as $lib) {
        $lib->prove();
    }

    foreach ($extNames as $name) {
        $ext = new Extension(name: $name, config: $config);
        $config->addExt($ext);
    }

    $build = new CliBuild($config);
    $build->build($allStatic);

    return 0;
}

exit(mian($argv));
