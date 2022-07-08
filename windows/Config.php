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
    public string $cmakeArch;
    public string $gnuArch;
    public string $cmakeGeneratorName;
    public string $configureEnv;
    public string $pkgconfEnv;
    public string $phpBinarySDKCmd;
    public string $cmakeToolchainFile;
    public int $concurrency;

    public static function fromCmdArgs(array $cmdArgs): static {
        return new static(
            phpBinarySDKDir: $cmdArgs['named']['phpBinarySDKDir'],
            vsVer: $cmdArgs['named']['vsVer'],
            arch: $cmdArgs['named']['arch'] ?? 'x64',
        );
    }

    public function __construct(
        public string $phpBinarySDKDir,
        public string $vsVer,
        public string $arch = 'x64',
    )
    {
        if (!($phpBinarySDKDir && $vsVer && $arch)) {
            throw new Exception('missong phpBinarySDKDir/vsVer/arch argument');
        }
        Log::i("build with VS $vsVer for $arch, SDK $phpBinarySDKDir");

        Log::i('mkdir deps');
        @mkdir('deps');

        $this->arch = match ($arch) {
            'x64', 'x86_64'  => 'x64',
            'arm64', 'aarch64' => 'arm64',
            //'x86', 'i386' => 'x86',
            default => throw new Exception("not supported arch {$arch}, supported: x64/arm64"),
        };

        $this->cmakeArch = match ($this->arch) {
            'x64' => 'x64',
            'arm64' => 'ARM64',
            //'x86' => 'Win32',
            default => throw new Exception("?????"),
        };
        
        $this->cmakeGeneratorName = match ($vsVer) {
            '14' => "Visual Studio 14 2015",
            '15' => "Visual Studio 15 2017",
            '16' => "Visual Studio 16 2019",
            '17' => "Visual Studio 17 2022",
            default => throw new Exception("not supported vs version {$vsVer} supported: 14,15,16,17"),
        };

        $this->cmakeToolchainFile = Util::makeCmakeToolchainFile($this->cmakeArch);

        $this->phpBinarySDKDir = rtrim($phpBinarySDKDir, '\\/');

        $crt = match ($vsVer) {
            '14' => 'vc14',
            '15' => 'vc15',
            '16' => 'vs16',
            '17' => 'vs17',
            default => throw new Exception("?????"),
        };
        $this->phpBinarySDKCmd = "\"{$this->phpBinarySDKDir}\\phpsdk-starter.bat\" -c {$crt} -a {$this->arch} ";

        Log::i("mkdir -p deps/include");
        @mkdir('deps/include', recursive: true);

        $this->concurrency = Util::getCpuCount();

        $this->gnuArch = Util::gnuArch($this->arch);
    }
}
