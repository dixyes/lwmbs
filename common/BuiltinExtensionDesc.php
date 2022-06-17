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
    public const BUILTIN_EXTENSIONS = [
        'opcache' => [],
        'phar' => [
            'libDeps' => ['zlib' => true],
        ],
        'mysqli' => [
            'argType' => 'with',
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
            'libDeps' => ['libxml' => false],
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
                'gd' => true,
                'zlib' => true,
                'libpng' => true,
                'libavif' => true,
                'libwebp' => true,
                'libjpeg' => true,
                'xpm' => true,
                'libfreetype' => true,
            ]
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
            'libDeps' => ['libiconv' => false],
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
            'extDeps' => ['pdo'],
        ],
        'pdo_mysql' => [
            'argType' => 'with',
            'extDeps' => ['pdo'],
        ],
        'pdo_pgsql' => [
            'argType' => 'with',
            'libDeps' => ['pq' => false],
            'extDeps' => ['pdo'],
        ],
        // todo: pdo other things
        'pspell' => [
            'argType' => 'with',
            'libDeps' => ['aspell' => false],
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
            'libDeps' => ['libxml' => false],
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
            'libDeps' => ['libxml' => false],
        ],
        'simplexml' => [
            'argTypeWin' => 'with',
            'libDeps' => ['libxml' => false],
        ],
        'xmlreader' => [
            'libDeps' => ['libxml' => false],
        ],
        'xmlwriter' => [
            'libDeps' => ['libxml' => false],
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
    private function __construct(
        public string $name,
        private array $libDeps = [],
        private array $extDeps = [],
        string $argType = 'enable',
    ) {
        $_name = str_replace('_', '-', $name);
        $this->arg = match ($argType) {
            'enable' => '--enable-' . $_name,
            'with' => '--with-' . $_name,
        };
        $this->disabledArg = match ($argType) {
            'enable' => '--disable-' . $_name,
            'with' => '--without-' . $_name,
        };
    }
    public static function getAll(): array
    {
        $ret = [];
        if (PHP_OS_FAMILY === 'Windows') {
            foreach (static::BUILTIN_EXTENSIONS as $name => $args) {
                if ($args['unixOnly'] ?? false) {
                    continue;
                }
                if (isset($args['argTypeWin'])) {
                    $args['argType'] = $args['argTypeWin'];
                    unset($args['argTypeWin']);
                }
                $ret[$name] = new static($name, ...$args);
            }
        } else {
            foreach (static::BUILTIN_EXTENSIONS as $name => $args) {
                $ret[$name] = new static($name, ...$args);
            }
        }
        return $ret;
    }
    public function getArg(bool $enabled = true): string
    {
        if ($enabled) {
            return $this->arg;
        } else {
            return $this->disabledArg;
        }
    }
    public function getExtDeps(): array
    {
        return $this->extDeps;
    }
    public function getLibDeps(): array
    {
        return $this->libDeps;
    }
    public function getCustomExtDir(): ?string
    {
        return null;
    }
}
