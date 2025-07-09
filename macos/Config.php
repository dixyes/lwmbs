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
    // TODO: comment
    //public string $noteSection = "Je pense, donc je suis\0";
    public string $cc;
    public string $cxx;
    public string $arch;
    public string $gnuArch;
    public string $cFlags;
    public string $cxxFlags;
    public ?string $sdkRoot;
    /**
     * @var array<string>
     */
    public array $extraFrameworks;
    public string $cmakeToolchainFile;

    public const NEEDED_COMMANDS = ['make', 'bison', 'flex', 'pkg-config', 'git', 'autoconf', 'automake', 'tar', 'unzip', 'xz', 'gzip', 'bzip2', 'cmake'];

    public static function fromCmdArgs(array $cmdArgs): static
    {
        return new static(
            cc: $cmdArgs['named']['cc'] ?? null,
            cxx: $cmdArgs['named']['cxx'] ?? null,
            arch: $cmdArgs['named']['arch'] ?? null,
            sdkRoot: $cmdArgs['named']['sdkRoot'] ?? null,
            extraFrameworks: $cmdArgs['named']['extraFrameworks'] ?? null,
        );
    }

    public function __construct(
        ?string $cc = null,
        ?string $cxx = null,
        ?string $arch = null,
        ?string $sdkRoot = null,
        ?string $extraFrameworks = null,
    ) {
        Log::i("check commands");
        $lackingCommands = Util::lackingCommands(static::NEEDED_COMMANDS);
        if ($lackingCommands) {
            throw new Exception("missing commands: " . implode(', ', $lackingCommands));
        }

        Log::i("mkdir -p lib/pkgconfig");
        @mkdir('lib/pkgconfig', recursive: true);
        Log::i("mkdir -p include");
        @mkdir('include', recursive: true);

        $this->cc = $cc ?? 'clang';
        Log::i('choose cc: ' . $this->cc);
        $this->cxx = $cxx ?? 'clang++';
        Log::i('choose cxx: ' . $this->cxx);
        $this->arch = $arch ?? php_uname('m');
        Log::i('choose arch: ' . $this->arch);
        $this->sdkRoot = $sdkRoot ? $sdkRoot : null;
        if ($this->sdkRoot === null && getenv('SDKROOT')) {
            $this->sdkRoot = getenv('SDKROOT');
        }
        Log::i('choose sdk root: ' . ($this->sdkRoot === null ? '<null, use default>' : $this->sdkRoot));
        if ($this->sdkRoot) {
            putenv("SDKROOT={$this->sdkRoot}");
        }

        $this->gnuArch = Util::gnuArch($this->arch);

        $this->concurrency = Util::getCpuCount();
        $this->cFlags = Util::getArchCFlags($this->arch);
        $this->cxxFlags = Util::getArchCFlags($this->arch);
        $this->cmakeToolchainFile = Util::makeCmakeToolchainFile(
            os: 'Darwin',
            targetArch: $this->arch,
            cflags: Util::getArchCFlags($this->arch),
            sdkRoot: $this->sdkRoot,
        );
        $this->extraFrameworks = $extraFrameworks ? array_map('trim', explode(',', $extraFrameworks)) : [];

        $this->configureEnv =
            'PKG_CONFIG_PATH="' . realpath('lib/pkgconfig') . '" ' .
            "CC='{$this->cc}' " .
            "CXX='{$this->cxx}' " .
            "CFLAGS='{$this->cFlags}'";
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

    public function getFrameworks(bool $asString = false): array|string
    {
        $libs = [];

        // reorder libs
        foreach ($this->libs as $lib) {
            foreach ($lib->getDependencies() as $_ => $dep) {
                array_push($libs, $dep);
            }
            array_push($libs, $lib);
        }

        $frameworks = [];
        foreach ($libs as $lib) {
            array_push($frameworks, ...$lib->getFrameworks());
        }
        $frameworks = array_merge($frameworks, $this->extraFrameworks);
        // the last duplicate framework should be kept, not the first occurrence
        $frameworks = array_reverse(array_unique(array_reverse($frameworks)));

        if($asString) {
            return implode(' ', array_map(fn($x)=>"-framework $x",$frameworks));
        }
        return $frameworks;
    }

    public function getCXXEnv(): string
    {
        if (str_ends_with($this->cxx, '++')) {
            return $this->cxx;
        }
        return "{$this->cxx} -x c++";
    }
}
