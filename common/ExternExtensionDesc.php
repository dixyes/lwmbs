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

class ExternExtensionDesc extends \stdClass implements ExtensionDesc
{
    use ExtensionDescTrait;
    public const EXTERN_EXTENSIONS = [
        'swoole' => [
            'unixOnly' => true,
            'extDeps' => [
                'openssl' => true,
                'curl' => true,
            ],
            'libDeps' => [
                'openssl' => false,
                'curl' => false,
            ],
        ],
        'swow' => [
            'extDir' => 'ext',
            'extDeps' => [
                'curl' => true,
                'openssl' => true,
            ],
            'libDeps' => [
                'openssl' => true,
                'curl' => true,
            ],
        ],
        'redis' => [],
        'yaml' => [
            'argType' => 'with',
            'libDeps' => [
                'libyaml' => false,
            ],
        ],
        'zstd' => [
            'libDeps' => [
                'zstd' => false,
            ],
        ],
        // todo:mongo
        //'mongodb' => [],
    ];
    private string $arg;
    private string $disabledArg;
    public static function getAll(): array
    {
        return static::_getAll(static::EXTERN_EXTENSIONS);
    }
}
