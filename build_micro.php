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

function build_micro($argv): int {
    Util::setErrorHandler();

    [$cmdArgs, $config] = Util::makeConfig($argv);

    foreach ($config->makeLibArray() as $_ => $lib) {
        $lib->prove(
            forceBuild: $cmdArgs['named']['noSystem'] ?? false,
            fresh: $cmdArgs['named']['fresh'] ?? false,
        );
    }

    $build = new MicroBuild($config);
    $build->build(
        fresh: $cmdArgs['named']['fresh'] ?? false,
        bloat: $cmdArgs['named']['bloat'] ?? false,
        fakeCli: $cmdArgs['named']['fakeCli'] ?? false,
    );
    return 0;
}

if (!isset($asLib)) {
    exit(build_micro($argv));
}
