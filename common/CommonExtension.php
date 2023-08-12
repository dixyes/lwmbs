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

class CommonExtension
{
    private static ?array $allExtensionDescs = null;

    public static function getAllExtensionDescs(): array
    {
        if (!static::$allExtensionDescs) {
            static::$allExtensionDescs = BuiltinExtensionDesc::getAll();
            static::$allExtensionDescs += ExternExtensionDesc::getAll();
        }
        return static::$allExtensionDescs;
    }

    protected ExtensionType $type;
    protected ExtensionDesc $desc;
    protected array $dependencies = [];
    public function __construct(
        protected string $name,
        protected Config $config,
    ) {
        $desc = static::getAllExtensionDescs()[$name] ?? null;
        if (!$desc) {
            throw new \Exception("Extension $name not implemented");
        }
        $this->desc = $desc;
    }
    public function checkDependency(): static {
        foreach ($this->desc->getLibDeps() as $name => $optional) {
            $this->addLibraryDependency($name, $optional);
        }
        foreach ($this->desc->getExtDeps() as $name => $optional) {
            $this->addExtensionDependency($name, $optional);
        }
        return $this;
    }
    public function getType(): ExtensionType
    {
        return $this->type;
    }
    public function getName(): string
    {
        return $this->name;
    }
    protected function addExtensionDependency(string $name, bool $optional = false): static
    {
        $depExt = $this->config->getExt($name);
        if (!$depExt) {
            if (!$optional) {
                throw new Exception("{$this->name} requires extension $name");
            } else {
                Log::i("enabling {$this->name} without extension $name");
            }
        } else {
            $this->dependencies[] = $depExt;
        }
        return $this;
    }
    public function getExtensionDependency(): array
    {
        return array_filter($this->dependencies, fn ($x) => $x instanceof CommonExtension);
    }
    protected function addLibraryDependency(string $name, bool $optional = false)
    {
        $depLib = $this->config->getLib($name);
        if (!$depLib) {
            if (!$optional) {
                throw new Exception("{$this->name} requires library $name");
            } else {
                Log::i("enabling {$this->name} without library $name");
            }
        } else {
            $this->dependencies[] = $depLib;
        }
    }
    public function getLibraryDependencies(bool $recursive = false): array
    {
        $ret = array_filter($this->dependencies, fn ($x) => $x instanceof Library);
        if (!$recursive) {
            return $ret;
        }

        $deps = [];

        $added = 1;
        while ($added !==0) {
            $added = 0;
            foreach ($ret as $depName => $dep) {
                foreach ($dep->getDependencies(true) as $depdepName => $depdep) {
                    if (!in_array($depdepName, array_keys($deps), true)) {
                        $deps[$depdepName] = $depdep;
                        $added++;
                    }
                }
                if (!in_array($depName, array_keys($deps), true)) {
                    $deps[$depName] = $dep;
                }
            }
        }

        return $deps;
    }
    public function useCPP(): bool
    {
        return $this->desc->useCPP;
    }
}
