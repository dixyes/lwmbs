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

class Liblibffi extends Library
{
    use WindowsLibraryTrait;
    protected string $name = 'libffi';
    protected array $staticLibs = [
        'libffi.lib',
    ];
    protected array $headers = [
        'ffi.h',
        'fficonfig.h',
        'ffitarget.h',
    ];
    protected array $depNames = [
    ];

    protected function build(): void
    {
        $ret = 0;
    
        if ($this->config->arch === 'x64') {
            Log::i("downloading libffi");
            $context = stream_context_create([ 'http' => [ 'header' => "user-agent: lwmbs/0.1\r\n" ] ]);
            $page = file_get_contents('https://windows.php.net/downloads/php-sdk/deps/vs16/x64/', context: $context);
            preg_match_all('/libffi-([^<>]+)-vs16-x64\.zip/', $page, $matches);
            if (!$matches) {
                throw new Exception('failed fetch download page');
            }
            $files = [];
            foreach ($matches[1] as $i => $version) {
                $files[$version] = $matches[0][$i];
            }
            uksort($files, 'version_compare');
            $file = array_pop($files);
            if (!file_exists("downloads/$file")) {
                $url = "https://windows.php.net/downloads/php-sdk/deps/vs16/x64/$file";
                Log::i("downloading $url");
                $context = stream_context_create([ 'http' => [ 'header' => "user-agent: lwmbs/0.1\r\n" ] ]);
                file_put_contents("downloads/$file", file_get_contents($url, context: $context));
            }

            $_7zExe = Util::findCommand('7z', [
                'C:\Program Files\7-Zip-Zstandard',
                'C:\Program Files (x86)\7-Zip-Zstandard',
                'C:\Program Files\7-Zip',
                'C:\Program Files (x86)\7-Zip',
            ]);
            if (!$_7zExe) {
                throw new Exception('needs 7z to unpack');
            }
            passthru("\"$_7zExe\" x -y downloads/$file -odeps/", $ret);
            if ($ret !== 0) {
                throw new Exception("failed to extract {$this->name}");
            }

            return;
        }

        Log::i("building {$this->name}");

        // patch vcxproj for build
        $vcxproj = file_get_contents('src\libffi\msvc_build\aarch64\Ffi_staticLib.vcxproj');
        // remove fixed win sdk requirement
        $vcxproj = preg_replace('|<WindowsTargetPlatformVersion>[^<]+</WindowsTargetPlatformVersion>|m', '<WindowsTargetPlatformVersion>10.0</WindowsTargetPlatformVersion>', $vcxproj);
        // update platform tools for vs 2019
        $vcxproj = preg_replace('|<PlatformToolset>v141</PlatformToolset>|m', '<PlatformToolset>v142</PlatformToolset>', $vcxproj);
        $vcxproj = str_replace('<WarningLevel>Level3</WarningLevel>', '<WarningLevel>Level3</WarningLevel><AdditionalOptions>/wd4146</AdditionalOptions>', $vcxproj);
        // 3.4 feature
        if (!str_contains($vcxproj, 'tramp.c')) {
            $vcxproj = str_replace('<ClCompile Include="..\..\src\closures.c" />', '<ClCompile Include="..\..\src\tramp.c" /><ClCompile Include="..\..\src\closures.c" />', $vcxproj);
        }
        file_put_contents('src\libffi\msvc_build\aarch64\Ffi_staticLib.vcxproj', $vcxproj);

        // get libffi version
        $version_texi = file_get_contents('src\libffi\doc\version.texi');
        preg_match('/@set VERSION (.+)/', $version_texi, $matches);
        if (!$matches) {
            throw new Exception('cannot determine libffi version');
        }
        $version = $matches[1];

        // update msvc_build\aarch64\aarch64_include\ffi.h
        $ffi_h = file_get_contents('src\libffi\include\ffi.h.in');
        foreach ([
            '@VERSION@' => $version,
            '@TARGET@' => 'AARCH64',
            '@HAVE_LONG_DOUBLE@' => '_M_ARM64',
            '@FFI_EXEC_TRAMPOLINE_TABLE@' => '0',
            // for static lib
            'defined FFI_BUILDING_DLL' => '0',
            '!defined FFI_BUILDING' => '0',
        ] as $macro => $value) {
            $ffi_h = preg_replace("/$macro/", $value, $ffi_h);
        }
        file_put_contents('src\libffi\msvc_build\aarch64\aarch64_include\ffi.h', $ffi_h);

        // update msvc_build\aarch64\aarch64_include\fficonfig.h
        $fficonfig_h = file_get_contents('src\libffi\msvc_build\aarch64\aarch64_include\fficonfig.h');
        $fficonfig_h = preg_replace('/3.3-rc0/', $version, $fficonfig_h);
        // 3.4 feature
        // if (!str_contains($fficonfig_h, 'FFI_EXEC_STATIC_TRAMP')) {
        //     $fficonfig_h .= "\n#define FFI_EXEC_STATIC_TRAMP 1";
        // }
        file_put_contents('src\libffi\msvc_build\aarch64\aarch64_include\fficonfig.h', $fficonfig_h);

        // patch src\dlmalloc.c for abort()
        $dlmalloc_c = file_get_contents('src\libffi\src\dlmalloc.c');
        $dlmalloc_c = preg_replace('/#include <windows.h>\n#define/m', "#include <windows.h>\n#include <stdlib.h>\n#define", $dlmalloc_c);
        file_put_contents('src\libffi\src\dlmalloc.c', $dlmalloc_c);

        file_put_contents('src/libffi/msvc_build/aarch64/msbuild_wrapper.bat', 'msbuild /nologo Ffi_staticLib.vcxproj -p:Configuration=Release -p:Platform=arm64 %*');

        passthru(
            "cd {$this->sourceDir}\\msvc_build\\aarch64 && " .
            "{$this->config->phpBinarySDKCmd} -t msbuild_wrapper.bat --task-args -t:Clean &&" .
            "{$this->config->phpBinarySDKCmd} -t msbuild_wrapper.bat --task-args -t:Build",
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to build {$this->name}");
        }

        copy('src\libffi\msvc_build\aarch64\aarch64_include\ffi.h', 'deps/include/ffi.h');
        copy('src\libffi\msvc_build\aarch64\aarch64_include\fficonfig.h', 'deps/include/fficonfig.h');
        copy('src\libffi\src\aarch64\ffitarget.h', 'deps/include/ffitarget.h');
        copy('src\libffi\msvc_build\aarch64\arm64\Release\Ffi_staticLib_arm64.lib', 'deps/lib/libffi.lib');
    }
}
