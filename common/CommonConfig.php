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
    protected array $exts = [];
    public bool $zts = true;
    public function addLib(Library $lib): static
    {
        $this->libs[$lib->getName()] = $lib;
        return $this;
    }

    public function getLib(string $name): ?Library
    {
        return $this->libs[$name] ?? null;
    }

    public function addExt(Extension $ext): static
    {
        $this->exts[$ext->getName()] = $ext;
        return $this;
    }

    public function getExt(string $name): ?Extension
    {
        return $this->exts[$name] ?? null;
    }

    public function useCPP(): bool
    {
        foreach ($this->exts as $ext) {
            if ($ext->useCPP()) {
                return true;
            }
        }
        foreach ($this->libs as $lib) {
            if ($lib->useCPP()) {
                return true;
            }
        }
        return false;
    }

    public function makeLibArray(): array
    {
        $ret = [];
        // at this time, all libraries registered
        foreach ($this->libs as $libName => $lib) {
            $lib->calcDependency();
        }
        foreach ($this->libs as $libName => $lib) {
            $deps = $lib->getDependencies(true);
            //var_dump($libName,array_keys($deps));
            foreach ($deps as $depName => $dep) {
                if (!in_array($depName, array_keys($ret), true)) {
                    //Log::i("add $depName as dependency of $libName");
                    $ret[$depName] = $dep;
                }
            }
            if (!in_array($libName, array_keys($ret), true)) {
                //Log::i("add $libName");
                $ret[$libName] = $lib;
            }
        }
        return $ret;
    }

    public function makeExtArray(): array
    {
        $ret = [];
        foreach ($this->exts as $extName => $ext) {
            $extDeps = $ext->checkDependency()->getExtensionDependency();
            foreach ($extDeps as $dep) {
                if (!in_array($dep->getName(), array_keys($ret), true)) {
                    $ret[$dep->getName()] = $this->getExt($dep->getName());
                }
            }
            if (!in_array($extName, array_keys($ret), true)) {
                $ret[$extName] = $ext;
            }
        }
        return $ret;
    }

    public function makeExtensionArgs(): string
    {
        $ret = [];
        $descs = CommonExtension::getAllExtensionDescs();
        foreach ($descs as $desc) {
            $ext = $this->exts[$desc->name] ?? null;
            if ($ext) {
                $ret[] = $ext->getExtensionEnabledArg();
            } else {
                $ret[] = $desc->getArg(false);
            }
        }
        return implode(' ', $ret);
    }
}
