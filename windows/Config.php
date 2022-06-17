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
    public string $cmakeGeneratorName;
    public string $configureEnv;
    public string $pkgconfEnv;
    public int $concurrency;

    public function __construct(
        public ?string $phpBinarySDKDir = null,
        public ?string $vsVer = null,
        public string $arch = 'x64',
    )
    {
        if (!($phpBinarySDKDir && $vsVer && $arch)) {
            throw new Exception('missong phpBinarySDKDir/vsVer/arch argument');
        }
        Log::i("build with VS $vsVer for $arch, SDK $phpBinarySDKDir");

        $this->arch = match ($arch) {
            'x64', 'x86_64'  => 'x64',
            'arm64', 'aarch64' => 'arm64',
            //'x86', 'i386' => 'x86',
        };

        $this->cmakeArch = match ($this->arch) {
            'x64' => 'x64',
            'arm64' => 'ARM64',
            //'x86' => 'Win32',
        };
        
        $this->cmakeGeneratorName = match ($vsVer) {
            '14' => "Visual Studio 14 2015",
            '15' => "Visual Studio 15 2017",
            '16' => "Visual Studio 16 2019",
            '17' => "Visual Studio 17 2022",
        };

        $this->phpBinarySDKDir = rtrim($phpBinarySDKDir, '\\/');

        Log::i("mkdir -p deps/include");
        @mkdir('deps/include', recursive: true);

        $this->concurrency = Util::getCpuCount();
    }
}
