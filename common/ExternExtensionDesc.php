<?php

class ExternExtensionDesc extends \stdClass implements ExtensionDesc
{
    public const EXTERN_EXTENSIONS = [
        'swoole' => [
            'libDeps' => [
                'openssl' => false,
                'curl' => false,
            ],
        ],
        'swow' => [
            'extDir' => 'ext',
            'libDeps' => [
                'openssl' => false,
                'curl' => false,
            ],
        ],
        'parallel' =>[],
        'redis' => [],
        // todo:mongo
        //'mongodb' => [],
    ];
    private string $arg;
    private function __construct(
        public string $name,
        public array $libDeps = [],
        public array $extDeps = [],
        private ?string $extDir = null,
        string $argType='enable',
    ) {
        $_name = str_replace('_', '-', $name);
        $this->arg = match ($argType) {
            'enable' => '--enable-' . $_name,
            'with' => '--with-' . $_name,
        };
        $this->dirName = $dirName ?? $name;
    }
    public static function getAll(): array
    {
        $ret =[];
        foreach (static::EXTERN_EXTENSIONS as $name=>$args) {
            $ret[$name]=new static($name, ...$args);
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
        return $this->extDir;
    }
}
