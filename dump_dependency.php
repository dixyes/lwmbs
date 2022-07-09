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

function dump_dependency($argv): int
{
    Util::setErrorHandler();

    $cmdArgs = Util::parseArgs(
        argv: $argv,
        positionalNames: [
            'srcFile' => ['SRCFILE', false, __DIR__ . DIRECTORY_SEPARATOR . 'src.json', 'src.json path'],
        ],
        namedKeys: [],
    );

    $srcJson = json_decode(file_get_contents($cmdArgs['positional']['srcFile']), true);

    $fakeConfig = Config::fromCmdArgs([
        'named' => [],
    ]);

    foreach ($srcJson['libs'] as $name => $_) {
        try {
            $lib = new ("Lib$name")($fakeConfig);
        } catch (Exception) {
            Log::i("skip not implemented lib $name");
            continue;
        }
        $fakeConfig->addLib($lib);
    }

    foreach ($fakeConfig->makeLibArray() as $name => $lib) {
        $srcJson['libs'][$name]['libDeps'] = $lib->getDepNames();
    }

    foreach ($srcJson['exts'] as $name => &$data) {
        $desc = BuiltinExtensionDesc::BUILTIN_EXTENSIONS[$name] ?? 
            ExternExtensionDesc::EXTERN_EXTENSIONS[$name] ?? null;
        if ($desc === null) {
            Log::i("skip not implemented ext $name");
            continue;
        }
        if ($desc['libDeps'] ?? null) {
            $data['libDeps'] = $desc['libDeps'];
        }
        if ($desc['libDepsWin'] ?? null) {
            $data['libDepsWin'] = $desc['libDepsWin'];
        }
        if ($desc['extDeps'] ?? null) {
            $data['extDeps'] = $desc['extDeps'];
        }
        if ($desc['unixOnly'] ?? null) {
            $data['unixOnly'] = $desc['unixOnly'];
        }
    }

    file_put_contents($cmdArgs['positional']['srcFile'], json_encode(value: $srcJson, flags: JSON_PRETTY_PRINT));

    return 0;
}

if (!isset($asLib)) {
    $asLib = true;
    exit(dump_dependency($argv));
}
