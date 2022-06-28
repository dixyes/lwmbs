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

    $namedKeys = match(PHP_OS_FAMILY) {
        'Windows', 'WINNT', 'Cygwin' => [
            'phpBinarySDKDir' => ['path to sdk', true, null, 'path to binary sdk'],
            'vsVer' => ['vs version', true, null, 'vs version, e.g. "17" for Visual Studio 2022'],
            'arch' => [ 'arch', false, 'x64', 'architecture, "x64" or "arm64:' ], // TODO: use real host arch
        ],
        'Darwin' => [
            'cc' => ['compiler', false, null, 'C compiler'],
            'cxx' => ['compiler', false, null, 'C++ compiler'],
            'arch' => [ 'arch', false, php_uname('m'), 'architecture'],
        ],
        'Linux' => [
            'cc' => ['compiler', false, null, 'C compiler'],
            'cxx' => ['compiler', false, null, 'C++ compiler'],
            'arch' => [ 'arch', false, php_uname('m'), 'architecture'],
            'all-static' => [ 'static', false, false, 'use -all-static in php build'],
        ]
    };

    $cmdArgs = Util::parseArgs(
        argv: $argv,
        positionalNames: [
            'libraries' => ['LIBRARIES', true, null, 'select libraries, comma separated'],
            'extensions' => ['EXTENSIONS', true, null, 'select extensions, comma separated'],
        ],
        namedKeys: $namedKeys,
    );

    $allStatic = (bool)($cmdArgs['named']['all-static']);

    $config = Config::fromCmdArgs($cmdArgs);

    $libNames = array_map('trim', explode(',', $cmdArgs['positional']['libraries']));
    [
        'zstd',
        'libssh2',
        'curl',
        'zlib',
        'brotli',
        //'libiconv',
        'libffi',
        'openssl',
        'libzip',
        'bzip2',
        'nghttp2',
        'onig',
        'libyaml',
        'xz',
    ];

    $extNames = array_map('trim', explode(',', $cmdArgs['positional']['extensions']));
    [
        'opcache',
        //'iconv',
        'bcmath',
        'pdo',
        'phar',
        'mysqlnd',
        'mysqli',
        'pdo',
        'pdo_mysql',
        'mbstring',
        'mbregex',
        'session',
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
        'zip',
        'bz2',
        'yaml',
        'zstd',
    ];
    if (PHP_OS_FAMILY !== 'Windows') {
        $extNames [] = 'pcntl';
        $extNames [] = 'posix';
    }

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

    $build = new MicroBuild($config);
    $build->build($allStatic);

    return 0;
}

exit(mian($argv));
