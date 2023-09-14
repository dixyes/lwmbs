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

trait CommonLibraryTrait
{
    public function __construct(
        private Config $config,
        private ?string $sourceDir = null,
        private array $dependencies = [],
        private readonly bool $useCPP = false,
    ) {
        $this->sourceDir = $sourceDir ?? ('src' . DIRECTORY_SEPARATOR . $this->name);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDepNames(): array
    {
        return $this->depNames;
    }

    public function getDependencies(bool $recursive = false): array
    {
        if (!$recursive) {
            return $this->dependencies;
        }

        $deps = [];

        $added = 1;
        while ($added !==0) {
            $added = 0;
            foreach ($this->dependencies as $depName => $dep) {
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

    public function calcDependency(): void
    {
        foreach ($this->depNames as $depName => $optional) {
            $this->addLibraryDependency($depName, $optional);
        }
    }

    protected function addLibraryDependency(string $name, bool $optional = false)
    {
        // Log::i("add $name as dep of {$this->name}");
        $depLib =$this->config->getLib($name);
        if (!$depLib) {
            if (!$optional) {
                throw new Exception("{$this->name} requires library $name");
            } else {
                Log::i("enabling {$this->name} without $name");
            }
        } else {
            $this->dependencies[$name] = $depLib;
        }
    }
    public function getStaticLibs(): array
    {
        return $this->staticLibs;
    }
    public function getHeaders(): array
    {
        return $this->headers;
    }
    public function useCPP(): bool
    {
        return $this->useCPP;
    }
}
