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

class CommonConfig extends \stdClass
{
    public int $concurrency = 1;
    protected array $libs = [];
    public bool $zts = true;
    public function addLib(Library $lib): void
    {
        $this->libs[$lib->getName()] = $lib;
    }

    public function getLib(string $name): ?Library
    {
        return $this->libs[$name] ?? null;
    }

    public function addExt(Extension $ext): void
    {
        $this->exts[$ext->getName()] = $ext;
    }

    public function getExt(string $name): ?Extension
    {
        return $this->exts[$name] ?? null;
    }

    public function makeLibArray(): array
    {
        $libNames = [];
        $ret = [];
        foreach ($this->libs as $libName => $lib) {
            $lib->calcDependency();
            $deps = $lib->getDependencies();
            foreach ($deps as $dep) {
                if (!in_array($dep->getName(), $libNames, true)) {
                    array_push($libNames, $dep->getName());
                    array_push($ret, $dep);
                }
            }
            if (!in_array($lib->getName(), $libNames, true)) {
                array_push($libNames, $lib->getName());
                array_push($ret, $lib);
            }
        }
        return $ret;
    }
}
