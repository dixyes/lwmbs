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
            'srcFile' => ['SRCFILE', true, null, 'src.json path'],
            'destDir' => ['DESTDIR', true, null, 'destination path'],
            'libraries' => ['LIBRARIES', false, null, 'libraries to generate, comma separated'],
        ],
        namedKeys: [],
    );

    $destDir = $cmdArgs['positional']['destDir'];

    if (!is_dir($destDir)) {
        mkdir($destDir);
    }

    $filter = fn ($k) => true;
    if ($cmdArgs['positional']['libraries']) {
        $names = explode(',', $cmdArgs['positional']['libraries']);
        $filter = function ($k) use ($names) {
            if (in_array($k, $names)) {
                return true;
            }
            return false;
        };
    }

    $data = json_decode(file_get_contents($cmdArgs['positional']['srcFile']), true);

    foreach (array_filter($data['src'], $filter, ARRAY_FILTER_USE_KEY) as $name => $info) {
        $license = $info['license'];
        Log::i("dump license for $name");
        switch ($license['type']) {
            case 'text':
                file_put_contents("{$destDir}/LICENSE.$name", $license['text']);
                break;
            case 'file':
                $srcPath = $info['path'] ?? $name;
                copy("src/{$srcPath}/{$license['path']}", "{$destDir}/LICENSE.$name");
                break;
            default:
                throw new Exception("unsupported license type {$license['type']}");
        }
    }
    if ($filter('php')) {
        Log::i("dump license for php");
        copy("src/php-src/LICENSE", "{$destDir}/LICENSE.php");
    }

    Log::i('done');

    return 0;
}

exit(mian($argv));
