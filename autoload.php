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

declare(strict_types=1);

spl_autoload_register(function ($class) {
    if (strpos($class, '\\') !== false) {
        // never here
        throw new Exception('???');
    }

    $osDir = match (PHP_OS_FAMILY) {
        'Windows', 'WINNT', 'Cygwin' => 'windows',
        'Linux' => 'linux',
        'Darwin' => 'macos',
    };

    if (str_starts_with($class, 'Lib') && $class !== 'Library') {
        $libName = substr($class, 3);
        $file = __DIR__ . "/$osDir/libraries/$libName.php";
        if (!is_file($file)) {
            throw new Exception("Library $libName not implemented: ");
        }
        require $file;
        return;
    }

    $file = __DIR__ . "/$osDir/$class.php";
    if (is_file($file)) {
        require $file;
    } else {
        require __DIR__ . "/common/$class.php";
    }
});
