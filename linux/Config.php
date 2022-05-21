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

class Config extends CommonConfig
{
    // TODO: workspace
    //public string $workspace = '.';
    public string $setX = 'set -x';
    public string $configureEnv = '';
    public string $noteSection = "Je pense, donc je suis\0";
    public CLib $libc;
    public string $arch;
    public array $tuneCFlags;

    const TUNE_CFLAGS = [
        '-march=corei7',
        '-mtune=core-avx2',
    ];

    public function __construct()
    {
        $lackingCommands = Util::lackingCommands(Util::NEEDED_COMMANDS);
        if ($lackingCommands) {
            throw new Exception("missing commands: " . implode(', ', $lackingCommands));
        }
        @mkdir('lib/pkgconfig', recursive: true);
        $this->configureEnv = 'PKG_CONFIG_PATH=' . realpath('lib/pkgconfig');
        $this->libc = Util::chooseLibc();
        $this->concurrency = Util::getCpuCount();
        $this->arch = php_uname('m');
        $this->tuneCFlags = Util::checkCCFlags(static::TUNE_CFLAGS);
    }

    public function makeAutoconfArgs(string $name, array $libSpecs): string
    {
        $ret = '';
        foreach ($libSpecs as $libName => $arr) {
            $lib = $this->getLib($libName);

            $arr = $arr ?? [];

            $disableArgs = $arr[0] ?? null;
            $prefix = $arr[1] ?? null;
            if ($lib) {
                Log::i("{$name} \033[32;1mwith\033[0;1m {$libName} support");
                $ret .= $lib->makeAutoconfEnv($prefix) . ' ';
            } else {
                Log::i("{$name} \033[31;1mwithout\033[0;1m {$libName} support");
                $ret .= ($disableArgs ?? "--with-$libName=no") . ' ';
            }
        }
        return rtrim($ret);
    }

    public function getAllStaticLibFiles(): array
    {
        $libs = [];

        // reorder libs
        foreach ($this->libs as $lib) {
            foreach ($lib->getDependencies() as $dep) {
                array_push($libs, $dep);
            }
            array_push($libs, $lib);
        }

        $libFiles = [];
        $libNames = [];
        // merge libs
        foreach ($libs as $lib) {
            if (!in_array($lib->getName(), $libNames, true)) {
                array_push($libNames, $lib->getName());
                array_unshift($libFiles, ...$lib->getStaticLibs());
            }
        }
        return array_map(fn ($x) => realpath("lib/$x"), $libFiles);
    }
}
