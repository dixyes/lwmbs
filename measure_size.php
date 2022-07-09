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

function measure_size($argv): int
{
    Util::setErrorHandler();

    $namedKeys = match (PHP_OS_FAMILY) {
        'Windows', 'WINNT', 'Cygwin' => [
            'phpBinarySDKDir' => ['path to sdk', true, null, 'path to binary sdk'],
            'vsVer' => ['vs version', true, null, 'vs version, e.g. "17" for Visual Studio 2022'],
            'arch' => ['arch', false, 'x64', 'architecture, "x64" or "arm64:'], // TODO: use real host arch
        ],
        'Darwin' => [
            'cc' => ['compiler', false, null, 'C compiler'],
            'cxx' => ['compiler', false, null, 'C++ compiler'],
            'arch' => ['arch', false, php_uname('m'), 'architecture'],
        ],
        'Linux' => [
            'cc' => ['compiler', false, null, 'C compiler'],
            'cxx' => ['compiler', false, null, 'C++ compiler'],
            'arch' => ['arch', false, php_uname('m'), 'architecture'],
            'noSystem' => ['BOOL', false, false, 'donot use system static libraries'],
            'allStatic' => ['BOOL', false, false, 'use -all-static in php build'],
        ]
    };

    $cmdArgs = Util::parseArgs(
        argv: $argv,
        positionalNames: [
            'libraries' => ['LIBRARIES', true, null, 'select libraries, comma separated'],
            'extensions' => ['EXTENSIONS', true, null, 'select extensions, comma separated'],
            'srcFile' => ['SRCFILE', false, __DIR__ . DIRECTORY_SEPARATOR . 'src.json', 'src.json path'],
        ],
        namedKeys: $namedKeys,
    );

    $config = Config::fromCmdArgs($cmdArgs);

    $srcJson = json_decode(file_get_contents($cmdArgs['positional']['srcFile']), true);

    $phpVerH = file_get_contents('src/php-src/main/php_version.h');
    preg_match('/#\s*define\s+PHP_MAJOR_VERSION\s+(\d+)/', $phpVerH, $matchesMajor);
    preg_match('/#\s*define\s+PHP_MINOR_VERSION\s+(\d+)/', $phpVerH, $matchesMinor);
    if (!$matchesMajor || !$matchesMinor) {
        throw new Exception('failed to find php version');
    }

    $phpVer = "{$matchesMajor[1]}.{$matchesMinor[1]}";

    $base = match (PHP_OS_FAMILY) {
        'Linux' =>
        "$phpVer-linux-{$config->gnuArch}-" . $config->libc->literalName() . ($cmdArgs['named']['allStatic'] ? '_static' : '_shared'),
        'Darwin' =>
        "$phpVer-macos-{$config->gnuArch}",
        'Windows' =>
        "$phpVer-windows-{$config->gnuArch}-vs{$config->vsVer}",
    };

    $libNames = array_filter(array_map('trim', explode(',', $cmdArgs['positional']['libraries'])));

    $extNames = array_filter(array_map('trim', explode(',', $cmdArgs['positional']['extensions'])));

    if ((bool)($cmdArgs['named']['allStatic'] ?? false)) {
        if (false !== array_search('libffi', $libNames, true)) {
            unset($libNames[array_search('libffi', $libNames, true)]);
        }
        if (false !== array_search('ffi', $extNames, true)) {
            unset($extNames[array_search('ffi', $extNames, true)]);
        }
    }

    // add all libs and exts to make order
    foreach ($libNames as $name) {
        $config->addLib(new ("Lib$name")($config));
    }

    foreach ($extNames as $name) {
        $config->addExt(new Extension(name: $name, config: $config));
    }

    $allLibs = $config->makeLibArray();
    $allExts = $config->makeExtArray();

    // make clean args
    array_splice($argv, 1, 3, ['', '', '--fresh']);

    $libs = [];
    $exts = [];

    // make size struct
    $srcJson['size'] = $srcJson['size'] ?? [];
    $srcJson['size'][$base]['libs'] = $srcJson['size'][$base]['libs'] ?? [];
    $srcJson['size'][$base]['exts'] = $srcJson['size'][$base]['exts'] ?? [];

    // make test config
    [$_, $testConfig] = Util::makeConfig($argv);
    $fresh = false;
    $build = function () use ($testConfig, $fresh) {
        $cliBuild = new CliBuild($testConfig);
        $cliBuild->build(
            fresh: $fresh,
            bloat: true,
        );
        clearstatcache(clear_realpath_cache: true, filename: 'src/php-src/sapi/cli/php');
        $stat = stat(match (PHP_OS_FAMILY) {
            'Windows', 'WINNT' => "src\\php-src\\{$testConfig->arch}\\Release_TS\\php.exe",
            default => 'src/php-src/sapi/cli/php',
        });
        return $stat['size'];
    };

    // try build base micro binary
    $microBuild = new MicroBuild($testConfig);
    $microBuild->build(
        fresh: true,
        bloat: true,
    );
    $stat = stat(match (PHP_OS_FAMILY) {
        'Windows', 'WINNT' => "src\\php-src\\{$testConfig->arch}\\Release_TS\\micro.sfx",
        default => 'src/php-src/sapi/micro/micro.sfx',
    });
    $srcJson['size'][$base]['micro'] = $stat['size'];
    Log::i("size of base micro is {$stat['size']}");

    // try build base cli binary
    $lastSize = $build($libs, $exts);

    $srcJson['size'][$base]['cli'] = $lastSize;
    Log::i("size of base cli is {$lastSize}");

    // try to add once a lib
    foreach ($allLibs as $name => $lib) {
        $testConfig->addLib($lib);
        $lib->prove(
            forceBuild: $cmdArgs['named']['noSystem'] ?? false,
            fresh: true,
        );
        $size = $build($libs, $exts);
        $delta = $size - $lastSize;
        $lastSize = $size;
        Log::i("size of library {$name} is {$delta}");
        $srcJson['size'][$base]['libs'][$name] = $delta;
    }

    //$allExtDesc = CommonExtension::getAllExtensionDescs();

    // try to add once an ext
    $fresh = true;
    foreach ($allExts as $name => $ext) {
        $testConfig->addExt($ext);
        $size = $build($libs, $exts);
        $delta = $size - $lastSize;
        $lastSize = $size;
        Log::i("size of extension {$name} is {$delta}");
        $srcJson['size'][$base]['exts'][$name] = $delta;
    }

    file_put_contents($cmdArgs['positional']['srcFile'], json_encode(value: $srcJson, flags: JSON_PRETTY_PRINT));

    return 0;
}

if (!isset($asLib)) {
    $asLib = true;
    exit(measure_size($argv));
}
