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

class BuiltinExtensionDesc extends \stdClass implements ExtensionDesc
{
    use ExtensionDescTrait;
    public const BUILTIN_EXTENSIONS = [
        'opcache' => [],
        'phar' => [
            'libDeps' => ['zlib' => true],
        ],
        'mysqlnd' => [
            'argTypeWin' => 'with',
        ],
        'mysqli' => [
            'argType' => 'with',
            'extDeps' => [
                'mysqlnd' => false,
            ],
        ],
        'mbstring' => [],
        'mbregex' => [
            'libDeps' => ['onig' => false],
        ],
        'session' => [],
        'pcntl' => [
            'unixOnly' => true,
        ],
        'posix' => [
            'unixOnly' => true,
        ],
        'ctype' => [],
        'fileinfo' => [],
        'filter' => [],
        'tokenizer' => [],
        'bcmath' => [],
        'bz2' => [
            'argType' => 'with',
            'libDeps' => ['bzip2' => false],
        ],
        'zlib' => [
            'argType' => 'with',
            'argTypeWin' => 'enable',
            'libDeps' => ['zlib' => false],
        ],
        'calendar' => [],
        //com dotnet
        'curl' => [
            'argType' => 'with',
            'libDeps' => ['curl' => false],
        ],
        'dba' => [
            'argTypeWin' => 'with',
        ],
        'dom' => [
            'argTypeWin' => 'with',
            'libDeps' => ['libxml2' => false],
        ],
        'enchant' => [
            'argType' => 'with',
            'libDeps' => ['enchant2' => false],
        ],
        'exif' => [],
        'ffi' => [
            'argType' => 'with',
            'libDeps' => ['libffi' => false],
        ],
        'ftp' => [
            'libDeps' => ['openssl' => true],
        ],
        'gd' => [
            'argTypeWin' => 'with',
            'libDeps' => [
                'zlib' => false,
                'libpng' => false,
                'libiconv' => true,
                'libjpeg' => true,
                'libfreetype' => true,
                'xpm' => true,
                'libavif' => true,
                'libwebp' => true,
            ],
        ],
        'gettext' => [
            'argType' => 'with',
            'libDeps' => ['gettext' => false],
        ],
        'gmp' => [
            'argType' => 'with',
            'libDeps' => ['gmp' => false],
        ],
        'iconv' => [
            'argType' => 'with',
            'libDepsWin' => ['libiconv' => false],
        ],
        'imap' => [
            'argType' => 'with',
            'libDeps' => [
                'imap' => false,
                'kerberos' => true,
            ],
        ],
        'intl' => [
            'libDeps' => ['icu' => false],
        ],
        'ldap' => [
            'argType' => 'with',
            'libDeps' => ['ldap' => false],
        ],
        // 'oci8' => [
        //     'argType' => 'with',
        //     'libDeps' => ['oci8' => false],
        // ],
        // todo: support this
        // 'odbc'=>[
        //     'libDeps'=>[
        //         'odbc'=>false,
        //         'adabas'=>true,
        //         'solid'=>true,
        //     ],
        // ],
        'openssl' => [
            'argType' => 'with',
            'libDeps' => ['openssl' => false],
        ],
        'pdo' => [],
        'pdo_sqlite' => [
            'argType' => 'with',
            'libDeps' => ['sqlite' => false],
            'extDeps' => [
                'pdo' => false,
            ],
        ],
        'pdo_mysql' => [
            'argType' => 'with',
            'extDeps' => [
                'pdo' => false,
                'mysqlnd' => false,
            ],
        ],
        'pdo_pgsql' => [
            'argType' => 'with',
            'libDeps' => [
                'pq' => false,
            ],
            'extDeps' => [
                'pdo' => false,
            ],
        ],
        // todo: pdo other things
        'pspell' => [
            'argType' => 'with',
            'libDeps' => [
                'aspell' => false
            ],
        ],
        'readline' => [
            'argType' => 'with',
            'libDeps' => [
                'readline' => false,
                'libedit' => true,
                'ncurses' => true,
            ],
        ],
        'shmop' => [],
        'snmp' => [
            'argType' => 'with',
            'libDeps' => ['net-snmp' => false],
        ],
        'soap' => [
            'libDeps' => ['libxml2' => false],
        ],
        'sockets' => [],
        'sodium' => [
            'argType' => 'with',
            'libDeps' => ['sodium' => false],
        ],
        'sqlite3' => [
            'argType' => 'with',
            'libDeps' => ['sqlite' => false],
        ],
        'sysvmsg' => [
            'unixOnly' => true,
        ],
        'sysvsem' => [
            'unixOnly' => true,
        ],
        'sysvshm' => [],
        'tidy' => [
            'argType' => 'with',
            'libDeps' => ['tidy' => false],
        ],
        'xml' => [
            'argTypeWin' => 'with',
            'libDeps' => ['libxml2' => false],
        ],
        'simplexml' => [
            'argTypeWin' => 'with',
            'libDeps' => ['libxml2' => false],
        ],
        'xmlreader' => [
            'libDeps' => ['libxml2' => false],
        ],
        'xmlwriter' => [
            'libDeps' => ['libxml2' => false],
        ],
        'xsl' => [
            'argType' => 'with',
            'libDeps' => ['libxslt' => false],
        ],
        'zip' => [
            'argType' => 'with',
            'argTypeWin' => 'enable',
            'libDeps' => ['libzip' => false],
        ],
    ];
    private string $arg;
    private string $disabledArg;
    public static function getAll(): array
    {
        return static::_getAll(static::BUILTIN_EXTENSIONS);
    }
}
