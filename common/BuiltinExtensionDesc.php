<?php

class BuiltinExtensionDesc extends \stdClass implements ExtensionDesc
{
    private string $arg;
    public const BUILTIN_EXTENSIONS = [
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
        'pcntl' => [],
        'posix' => [],
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
            'libDeps' => ['zlib' => false],
        ],
        'calendar' => [],
        //com dotnet
        'curl' => [
            'argType' => 'with',
            'libDeps' => ['curl' => false],
        ],
        'dba' => [],
        'dom' => [
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
        'libxml' => [
            'argType' => 'with',
            'libDeps' => ['libxml2' => false],
        ],
        'oci8' => [
            'argType' => 'with',
            'libDeps' => ['oci8' => false],
        ],
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
        'sysvmsg' => [],
        'sysvsem' => [],
        'sysvshm' => [],
        'tidy' => [
            'argType' => 'with',
            'libDeps' => ['tidy' => false],
        ],
        'xml' => [
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
            'libDeps' => ['libzip' => false],
        ],
    ];
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
    }
    public static function getAll(): array
    {
        $ret = [];
        foreach (static::BUILTIN_EXTENSIONS as $name => $args) {
            $ret[$name] = new static($name, ...$args);
        }
        return $ret;
    }
    public function getArg(): string
    {
        return $this->arg;
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
