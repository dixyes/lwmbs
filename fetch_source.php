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

require __DIR__ . '/common/Log.php';
require __DIR__ . '/common/LogType.php';
require __DIR__ . '/common/CommonUtilTrait.php';
require __DIR__ . '/common/FetcherUtilTrait.php';
require __DIR__ . '/common/Fetcher/SourceCode.php';
require __DIR__ . '/common/Fetcher/SourceCodeSource.php';

class Util
{
    use CommonUtilTrait;
    use FetcherUtilTrait;
}

function mian($argv): int
{
    Util::setErrorHandler();

    $cmdArgs = Util::parseArgs(
        argv: $argv,
        positionalNames: [
            'libraries' => ['LIBRARIES', false, '', 'libraries used, comma spearated, empty for all'],
            'extensions' => ['EXTENSIONS', false, '', 'extensions used, comma spearated, empty for all'],
        ],
        namedKeys: [
            'hash' => ['BOOL', false, false, 'hash only'],
            'versionFile' => ['PATH', false, null, 'output versions to a file'],
            'shallowClone' => ['BOOL', false, false, 'use shallow clone'],
            'srcFile' => ['SRCFILE', false, __DIR__ . DIRECTORY_SEPARATOR . 'src.json', 'src.json path'],
            'phpVer' => ['VERSION', false, '8.2', 'php version in major.minor format like 8.2'],
        ],
    );

    $phpVer = $cmdArgs['named']['phpVer'];

    preg_match('/^\d+\.\d+$/', $phpVer, $matches);
    if (!$matches) {
        Log::e("bad version arg: {$phpVer}\n");
        return 1;
    }

    $phpRef = Util::latestPHP($phpVer);

    if ($cmdArgs['named']['hash']) {
        Log::$outFd = STDERR;
    }

    $data = json_decode(file_get_contents($cmdArgs['named']['srcFile']), true);

    $chosen = [
        'php',
        'micro',
    ];
    $libraries = array_map('trim', array_filter(explode(',', $cmdArgs['positional']['libraries'])));
    if ($libraries) {
        foreach ($libraries as $lib) {
            $srcName = $data['libs'][$lib]['source'];
            $chosen[] = $srcName;
        }
    } else {
        $chosen = [...$chosen, ...array_map(fn ($x) => $x['source'], array_values($data['libs']))];
    }
    $extensions = array_map('trim', array_filter(explode(',', $cmdArgs['positional']['extensions'])));
    if ($extensions) {
        foreach ($extensions as $lib) {
            $srcName = $data['exts'][$lib]['source'];
            $chosen[] = $srcName;
        }
    } else {
        $chosen = [...$chosen, ...array_map(fn ($x) => $x['source'], array_values($data['exts']))];
    }
    $chosen = array_unique($chosen);
    $filter = fn ($_, $name) => in_array($name, $chosen, true);

    $versionFile = fopen($cmdArgs['named']['versionFile'] ?? "php://memory", 'w+');

    $shallowClone = (bool)$cmdArgs['named']['shallowClone'];

    @mkdir('downloads');

    /** @var GitSourceCodeSource $phpSCS */
    $phpSCS = SourceCodeSource::fromConfig('php', [
        "type" => "git",
        "path" => "php-src",
        "url" => "https://github.com/php/php-src",
        "ref" => $phpRef,
    ]);
    $phpSC = $phpSCS->download();
    fwrite($versionFile, "{$phpSCS->versionLine()}\n");
    if (!$cmdArgs['named']['hash']) {
        $phpSCS->clone('src/php-src', shallowClone: $shallowClone);
    }

    ksort($data["src"]);

    foreach (array_filter($data["src"], $filter, ARRAY_FILTER_USE_BOTH) as $name => $config) {
        // var_dump($name, $config);
        $scs = SourceCodeSource::fromConfig($name, $config);
        fwrite($versionFile, "{$scs->versionLine()}\n");
        if ($cmdArgs['named']['hash']) {
            continue;
        }
        $sc = $scs->download();
        $sc->prepare(shallowClone: $shallowClone);
    }

    if (!$cmdArgs['named']['hash']) {
        $phpSC->prepare();
    } else {
        $versionLines = stream_get_contents($versionFile, offset: 0);
        printf("%s\n", hash('sha256', $versionLines));
    }
    fclose($versionFile);

    Log::i('done');

    return 0;
}

exit(mian($argv));
