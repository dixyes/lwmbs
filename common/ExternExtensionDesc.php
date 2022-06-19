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
    public const EXTERN_EXTENSIONS = [
        'swoole' => [
            'unixOnly' => true,
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
        'parallel' =>[
            'argTypeWin' => 'with',
        ],
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
        $this->disabledArg = match ($argType) {
            'enable' => '--disable-' . $_name,
            'with' => '--without-' . $_name,
        };
        $this->dirName = $dirName ?? $name;
    }
    public static function getAll(): array
    {
        $ret = [];
        if (PHP_OS_FAMILY === 'Windows') {
            foreach (static::EXTERN_EXTENSIONS as $name => $args) {
                if ($args['unixOnly'] ?? false) {
                    continue;
                }
                if (isset($args['argTypeWin'])) {
                    $args['argType'] = $args['argTypeWin'];
                    unset($args['argTypeWin']);
                }
                unset($args['winOnly']);
                $ret[$name] = new static($name, ...$args);
            }
        } else {
            foreach (static::EXTERN_EXTENSIONS as $name => $args) {
                if ($args['winOnly'] ?? false) {
                    continue;
                }
                unset($args['unixOnly']);
                unset($args['argTypeWin']);
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
        return $this->extDir;
    }
}
