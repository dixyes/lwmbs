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

class Libopenssl extends Library
{
    use WindowsLibraryTrait;
    protected string $name = 'openssl';
    protected array $staticLibs = [
        'libssl.lib',
        'libcrypto.lib',
    ];
    protected array $headers = [
        'openssl',
    ];
    protected array $depNames = [
        'zlib' => true,
    ];

    protected function build(): void
    {
        Log::i("building {$this->name}");
        
        $confArch = match($this->config->arch) {
            'x64' => 'VC-WIN64A',
            'arm64' => 'VC-WIN64-ARM',
            //'ia64' => 'VC-WIN64I', really?
            default => throw new Exception("not supported arch {$this->config->arch}"),
        };
    
        $zlib = '';
        $libzlib = $this->config->getLib('zlib');
        //var_dump($libzlib);
        if ($libzlib) {
            Log::i("{$this->name} with zlib support");
            $zlib = "zlib";
            $zlib_ldflags =  '/LIBPATH:\"' . realpath('deps/lib') . '\" zlibstatic.lib';
        }

        file_put_contents('src/openssl/nmake_wrapper.bat', 'nmake /nologo CNF_LDFLAGS="/NODEFAULTLIB:kernel32.lib /NODEFAULTLIB:msvcrt /NODEFAULTLIB:msvcrtd /DEFAULTLIB:libcmt ' . $zlib_ldflags . '" %*');

        $ret = 0;
        passthru(
            "cd {$this->sourceDir} && " .
                "perl Configure $zlib $confArch " .
                    "disable-shared " .
                    '--prefix="' . realpath('deps') . '" ' .
                    '-I"' . realpath('deps\include'). '" '.
                    '--release && ' .
                $this->config->phpBinarySDKCmd . ' -t nmake_wrapper.bat --task-args clean && '.
                $this->config->phpBinarySDKCmd . ' -t nmake_wrapper.bat --task-args install' .
                (0 ? '' : '_dev')
                ,
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to build {$this->name}");
        }
    }
}
