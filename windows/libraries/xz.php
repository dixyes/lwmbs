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

class Libxz extends Library
{
    use WindowsLibraryTrait;
    protected string $name = 'xz';
    const ARM64_PATCH = <<<'EOF'
diff -urN xz/windows/vs2019/liblzma.vcxproj xz.new/windows/vs2019/liblzma.vcxproj
--- xz/windows/vs2019/liblzma.vcxproj	2020-03-17 22:28:54.000000000 +0800
+++ xz.new/windows/vs2019/liblzma.vcxproj	2022-06-18 12:04:57.645242500 +0800
@@ -17,6 +17,10 @@
       <Configuration>ReleaseMT</Configuration>
       <Platform>x64</Platform>
     </ProjectConfiguration>
+    <ProjectConfiguration Include="ReleaseMT|arm64">
+      <Configuration>ReleaseMT</Configuration>
+      <Platform>arm64</Platform>
+    </ProjectConfiguration>
     <ProjectConfiguration Include="Release|Win32">
       <Configuration>Release</Configuration>
       <Platform>Win32</Platform>
@@ -62,6 +66,11 @@
     <UseDebugLibraries>false</UseDebugLibraries>
     <PlatformToolset>v142</PlatformToolset>
   </PropertyGroup>
+  <PropertyGroup Condition="'$(Configuration)|$(Platform)'=='ReleaseMT|arm64'" Label="Configuration">
+    <ConfigurationType>StaticLibrary</ConfigurationType>
+    <UseDebugLibraries>false</UseDebugLibraries>
+    <PlatformToolset>v142</PlatformToolset>
+  </PropertyGroup>
   <Import Project="$(VCTargetsPath)\Microsoft.Cpp.props" />
   <ImportGroup Label="ExtensionSettings">
   </ImportGroup>
@@ -83,6 +92,9 @@
   <ImportGroup Condition="'$(Configuration)|$(Platform)'=='ReleaseMT|x64'" Label="PropertySheets">
     <Import Project="$(UserRootDir)\Microsoft.Cpp.$(Platform).user.props" Condition="exists('$(UserRootDir)\Microsoft.Cpp.$(Platform).user.props')" Label="LocalAppDataPlatform" />
   </ImportGroup>
+  <ImportGroup Condition="'$(Configuration)|$(Platform)'=='ReleaseMT|arm64'" Label="PropertySheets">
+    <Import Project="$(UserRootDir)\Microsoft.Cpp.$(Platform).user.props" Condition="exists('$(UserRootDir)\Microsoft.Cpp.$(Platform).user.props')" Label="LocalAppDataPlatform" />
+  </ImportGroup>
   <PropertyGroup Label="UserMacros" />
   <PropertyGroup Condition="'$(Configuration)|$(Platform)'=='Debug|Win32'">
     <LinkIncremental>true</LinkIncremental>
@@ -114,6 +126,11 @@
     <OutDir>$(SolutionDir)$(Configuration)\$(Platform)\$(ProjectName)\</OutDir>
     <IntDir>$(Configuration)\$(Platform)\$(ProjectName)\</IntDir>
   </PropertyGroup>
+  <PropertyGroup Condition="'$(Configuration)|$(Platform)'=='ReleaseMT|arm64'">
+    <LinkIncremental>true</LinkIncremental>
+    <OutDir>$(SolutionDir)$(Configuration)\$(Platform)\$(ProjectName)\</OutDir>
+    <IntDir>$(Configuration)\$(Platform)\$(ProjectName)\</IntDir>
+  </PropertyGroup>
   <ItemDefinitionGroup Condition="'$(Configuration)|$(Platform)'=='Debug|Win32'">
     <ClCompile>
       <PreprocessorDefinitions>WIN32;HAVE_CONFIG_H;_DEBUG;_LIB;%(PreprocessorDefinitions)</PreprocessorDefinitions>
@@ -194,6 +211,21 @@
     <ClCompile>
       <PreprocessorDefinitions>WIN32;HAVE_CONFIG_H;NDEBUG;_LIB;%(PreprocessorDefinitions)</PreprocessorDefinitions>
       <RuntimeLibrary>MultiThreaded</RuntimeLibrary>
+      <WarningLevel>Level3</WarningLevel>
+      <DebugInformationFormat>ProgramDatabase</DebugInformationFormat>
+      <AdditionalIncludeDirectories>./;../../src/liblzma/common;../../src/common;../../src/liblzma/api;../../src/liblzma/check;../../src/liblzma/delta;../../src/liblzma/lz;../../src/liblzma/lzma;../../src/liblzma/rangecoder;../../src/liblzma/simple</AdditionalIncludeDirectories>
+    </ClCompile>
+    <Link>
+      <GenerateDebugInformation>true</GenerateDebugInformation>
+      <SubSystem>Windows</SubSystem>
+      <EnableCOMDATFolding>true</EnableCOMDATFolding>
+      <OptimizeReferences>true</OptimizeReferences>
+    </Link>
+  </ItemDefinitionGroup>
+  <ItemDefinitionGroup Condition="'$(Configuration)|$(Platform)'=='ReleaseMT|arm64'">
+    <ClCompile>
+      <PreprocessorDefinitions>WIN32;HAVE_CONFIG_H;NDEBUG;_LIB;%(PreprocessorDefinitions)</PreprocessorDefinitions>
+      <RuntimeLibrary>MultiThreaded</RuntimeLibrary>
       <WarningLevel>Level3</WarningLevel>
       <DebugInformationFormat>ProgramDatabase</DebugInformationFormat>
       <AdditionalIncludeDirectories>./;../../src/liblzma/common;../../src/common;../../src/liblzma/api;../../src/liblzma/check;../../src/liblzma/delta;../../src/liblzma/lz;../../src/liblzma/lzma;../../src/liblzma/rangecoder;../../src/liblzma/simple</AdditionalIncludeDirectories>
EOF;
    protected array $staticLibs = [
        'liblzma.lib',
        'liblzma_a.lib',
    ];
    protected array $headers = [
        'lzma',
        'lzma.h',
    ];
    protected array $depNames = [
        'libiconv' => true,
    ];

    protected function build(): void
    {
        Log::i("building {$this->name}");

        $msbuildDir = match ($this->config->vsVer) {
            '14' => 'vs2013',
            '15' => 'vs2017',
            '16' => 'vs2019',
            '17' => 'vs2019',
            default => throw new Exception("?????"),
        };

        $ret = 0;

        if ($this->config->arch === 'arm64') {
            if ((int)$this->config->vsVer < 16) {
                throw new Exception("vs {$this->config->vsVer} does not support arm64");
            }
            Log::i('patching for xz arm64');

            file_put_contents('src/xz/arm64.patch', static::ARM64_PATCH);
            // simple patch
            passthru(
                "cd {$this->sourceDir} && type arm64.patch | patch -p1",
                $ret
            );
        }

        file_put_contents('src/xz/msbuild_wrapper.bat', "msbuild windows\\$msbuildDir\\liblzma.vcxproj -p:Configuration=ReleaseMT %*");

        passthru(
            "cd {$this->sourceDir} && " .
                $this->config->phpBinarySDKCmd . ' -t msbuild_wrapper.bat --task-args -t:Clean && '.
                $this->config->phpBinarySDKCmd . ' -t msbuild_wrapper.bat --task-args -t:Build'
            ,
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to build {$this->name}");
        }

        copy("src\\xz\\windows\\vs2019\\ReleaseMT\\{$this->config->arch}\\liblzma\\liblzma.pdb", 'deps\lib\liblzma.pdb');
        copy("src\\xz\\windows\\vs2019\\ReleaseMT\\{$this->config->arch}\\liblzma\\liblzma.lib", 'deps\lib\liblzma.lib');
        copy("src\\xz\\windows\\vs2019\\ReleaseMT\\{$this->config->arch}\\liblzma\\liblzma.lib", 'deps\lib\liblzma_a.lib');
    
        copy('src\xz\src\liblzma\api\lzma.h', 'deps\include\lzma.h');
        // patch lzma.h
        $lzma_h = file_get_contents('deps\include\lzma.h');
        $lzma_h = preg_replace('/defined\s*\(\s*LZMA_API_STATIC\s*\)/', '1', $lzma_h);
        file_put_contents('deps\include\lzma.h', $lzma_h);

        Util::copyDir('src\xz\src\liblzma\api\lzma', 'deps\include\lzma');
    }
}
