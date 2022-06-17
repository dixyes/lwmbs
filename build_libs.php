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
            'cc',
            'cxx',
            'arch',
            'phpBinarySDKDir',
            'vsVer',
        ], true)) {
            Log::e("Unknown argument: $k");
            if (PHP_OS_FAMILY === 'Windows') {
                Log::w("Usage: {$argv[0]} --phpBinarySDKDir=<path to sdk> --vsVer=<vs version> [--arch=<arch>]");
            } else {
                Log::w("Usage: {$argv[0]} [--cc=<compiler>] [--cxx=<compiler>] [--arch=<arch>]");
            }
            exit(1);
        }
    }

    $unsupportedArgs = [];
    switch (PHP_OS_FAMILY) {
        case 'Windows':
            $unsupportedArgs = ['cc', 'cxx'];
            break;
        case 'Darwin':
        case 'Linux':
            $unsupportedArgs = ['phpBinarySDKDir'];
            break;
    }
    foreach ($unsupportedArgs as $unsupportedArg) {
    $cmdArgs = Util::parseArgs($argv);
        if (array_key_exists($unsupportedArg, $cmdArgs)) {
            log::w("unsupported $unsupportedArg arg for " . PHP_OS_FAMILY);
            unset($cmdArgs[$unsupportedArg]);
        }
    }

    $config = new Config(
        ...$cmdArgs,
    );

    $libNames = [
        // 'zstd',
        // 'libssh2',
        // 'curl',
        'zlib',
        // 'brotli',
        // 'libiconv',
        // 'libffi',
        // 'openssl',
        // 'libzip',
        // 'bzip2',
        // 'nghttp2',
        // 'onig',
        // 'xz',
    ];
    foreach ($libNames as $name) {
        $lib = new ("Lib$name")($config);
        $config->addLib($lib);
    }
    //var_dump(array_map(fn($x)=>$x->getName(),$config->makeLibArray()));

    foreach ($config->makeLibArray() as $lib) {
        $lib->prove();
    }

    return 0;
}

exit(mian($argv));
