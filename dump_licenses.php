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
require __DIR__ . '/common/FetcherUtilTrait.php';
require __DIR__ . '/common/CommonUtilTrait.php';

class Util
{
    use CommonUtilTrait;
}

function mian($argv): int
{
    Util::setErrorHandler();

    $cmdArgs = Util::parseArgs(
        argv: $argv,
        positionalNames: [
            'destDir' => ['DESTDIR', true, null, 'destination path'],
            'libraries' => ['LIBRARIES', false, '', 'libraries to generate, comma separated, empty for all'],
            'extensions' => ['EXTENSIONS', false, '', 'extensions to generate, comma separated, empty for all'],
        ],
        namedKeys: [
            'srcFile' => ['SRCFILE', false, __DIR__ . DIRECTORY_SEPARATOR . 'src.json', 'src.json path'],
        ],
    );

    $destDir = $cmdArgs['positional']['destDir'];

    if (!is_dir($destDir)) {
        mkdir($destDir);
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

    foreach (array_filter($data['src'], $filter, ARRAY_FILTER_USE_BOTH) as $name => $info) {
        $license = $info['license'];
        Log::i("dump license for $name");
        switch ($license['type']) {
            case 'text':
                file_put_contents("{$destDir}/LICENSE.$name", $license['text']);
                break;
            case 'file':
                $srcPath = $info['path'] ?? $name;
                if (!is_file("src/{$srcPath}/{$license['path']}")) {
                    Log::w("license file not found: src/{$srcPath}/{$license['path']}");
                    break;
                }
                copy("src/{$srcPath}/{$license['path']}", "{$destDir}/LICENSE.$name");
                break;
            default:
                throw new Exception("unsupported license type {$license['type']}");
        }
    }
    if ($filter('', 'php')) {
        Log::i("dump license for php");
        copy("src/php-src/LICENSE", "{$destDir}/LICENSE.php");
    }

    Log::i('done');

    return 0;
}

exit(mian($argv));
