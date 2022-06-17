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
    use SourceTrait;

    public function __construct(
        private Config $config,
        ?string $sourceDir = null,
        private array $dependencies = [],
    ) {
        $this->sourceDir = $sourceDir ?? ('src' .DIRECTORY_SEPARATOR . $this->name);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDependencies(bool $recursive = false): array
    {
        $ret = $this->dependencies;
        if (!$recursive) {
            return $ret;
        }

        $added = 1;
        while ($added !==0) {
            $added = 0;
            foreach ($ret as $dep) {
                foreach ($dep->getDependencies(true) as $depdep) {
                    if (!in_array($depdep, $ret, true)) {
                        array_push($ret, $depdep);
                        $added++;
                    }
                }
            }
        }

        return $ret;
    }

    public function calcDependency(): void
    {
        foreach ($this->depNames as $depName => $optional) {
            $this->addLibraryDependency($depName, $optional);
        }
    }

    private function addLibraryDependency(string $name, bool $optional = false)
    {
        $depLib =$this->config->getLib($name);
        if (!$depLib) {
            if (!$optional) {
                throw new Exception("{$this->name} requires library $name");
            } else {
                Log::i("enabling {$this->name} without $name");
            }
        } else {
            $this->dependencies[] = $depLib;
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
}
