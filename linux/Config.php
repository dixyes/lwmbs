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
    public string $configureEnv;
    public string $pkgconfEnv;
    public string $noteSection = "Je pense, donc je suis\0";
    public CLib $libc;
    public CXXLib $libcxx;
    public string $cc;
    public string $cxx;
    public string $arch;
    public string $gnuArch;
    public string $cFlags;
    public string $cxxFlags;
    public array $tuneCFlags;
    public string $cmakeToolchainFile;

    public string $crossCompilePrefix = '';

    public const NEEDED_COMMANDS = ['make', 'bison', 'flex', 'pkg-config', 'git', 'autoconf', 'automake', 'tar', 'unzip', 'xz', 'gzip', 'bzip2', 'cmake'];

    public static function fromCmdArgs(array $cmdArgs): static {
        return new static(
            cc: $cmdArgs['named']['cc'] ?? null,
            cxx: $cmdArgs['named']['cxx'] ?? null,
            arch: $cmdArgs['named']['arch'] ?? null,
            allStatic: (bool)($cmdArgs['named']['allStatic'] ?? false),
        );
    }

    public function __construct(
        ?string $cc=null,
        ?string $cxx=null,
        ?string $arch=null,
        public bool $allStatic=false,
    )
    {
        Log::i("check commands");
        $lackingCommands = Util::lackingCommands(static::NEEDED_COMMANDS);
        if ($lackingCommands) {
            throw new Exception("missing commands: " . implode(', ', $lackingCommands));
        }

        Log::i("mkdir -p lib/pkgconfig");
        @mkdir('lib/pkgconfig', recursive: true);
        Log::i("mkdir -p include");
        @mkdir('include', recursive: true);

        $this->cc = $cc ?? Util::chooseCC();
        Log::i('choose cc: ' . $this->cc);
        $this->cxx = $cxx ?? Util::chooseCXX();
        Log::i('choose cxx: ' . $this->cxx);
        $this->arch = $arch ?? php_uname('m');
        Log::i('choose arch: ' . $this->arch);

        $this->libc = Util::chooseLibc($this->cc);
        $this->libcxx = Util::chooseLibcxx($this->cc, $this->cxx);
        $this->concurrency = Util::getCpuCount();
        $this->cFlags = Util::getArchCFlags($this->cc, $this->arch);
        $this->cxxFlags = Util::getArchCFlags($this->cxx, $this->arch);
        switch (Util::getCCType($this->cxx)) {
            case 'clang':
                if ($this->libcxx == CXXLib::LIBCXX) {
                    $this->cxxFlags .= ' -stdlib=libc++';
                }
                break;
            case 'gcc':
                // $this->cxxFlags .= ' -static-libstdc++ -static-libgcc';
                break;
        }
        $this->tuneCFlags = Util::checkCCFlags(util::getTuneCFlags($this->arch), $this->cc);
        $this->cmakeToolchainFile = Util::makeCmakeToolchainFile(
            os: 'Linux',
            targetArch: $this->arch,
            cflags: Util::getArchCFlags($this->cc, $this->arch),
            cc: $this->cc,
            cxx: $this->cxx,
        );
        
        $this->gnuArch = Util::gnuArch($this->arch);

        $this->pkgconfEnv = 
            'PKG_CONFIG_PATH="' . realpath('lib/pkgconfig') . '"';
        $this->configureEnv = 
            $this->pkgconfEnv . ' ' .
            "CC='{$this->cc}' " .
            "CXX='{$this->cxx}' " .
            (php_uname('m') === $arch?'':"CFLAGS='{$this->cFlags}'");
        if (php_uname('m') !== $this->arch){
            $this->crossCompilePrefix =Util::getCrossCompilePrefix($this->cc, $this->arch);
            Log::i('using cross compile prefix ' . $this->crossCompilePrefix);
            $this->configureEnv .= " CROSS_COMPILE='{$this->crossCompilePrefix}'";
        }
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
            foreach ($lib->getDependencies() as $_ => $dep) {
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

    public function getCCEnv(bool $usedCXX = false): string
    {
        return match ($this->libc) {
            CLib::GLIBC => '',
            CLib::MUSL => '',
            CLib::MUSL_WRAPPER => 
                'CC=' . $this->cc . ($usedCXX ? ' CXX="' . $this->cxx . '"' : ''),
        };
    }
}
