<?php
/**
 * Copyright (c) 2023 Yun Dou <dixyes@gmail.com>
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

class Libwebview extends Library
{
    use WindowsLibraryTrait;
    protected string $name = 'webview';
    protected array $staticLibs = [
        'webview_static.lib',
        'WebView2LoaderStatic.lib'
    ];
    protected array $headers = [
    ];
    protected array $depNames = [
    ];

    protected function build(): void
    {
        Log::i("building {$this->name}");

        if (!file_exists('downloads/webview2.nupkg')) {
            // download WebView2LoaderStatic from nuget
            // todo: use src fetcher to download it ?

            Log::i("downloading webview2 from nupkg");
            Util::download(
                url: 'https://www.nuget.org/api/v2/package/Microsoft.Web.WebView2',
                path: 'downloads/webview2.nupkg',
            );

            // extract WebView2LoaderStatic.lib from nuget package using pwsh
            $ret = 0;
            passthru(
                "pwsh -Command \"Expand-Archive -Path downloads/webview2.nupkg -DestinationPath src/webview/webview2 -Force\"",
                $ret,
            );
            if ($ret !== 0) {
                throw new Exception("failed to extract webview2 from nupkg");
            }
        }

        // build webview
        file_put_contents("{$this->sourceDir}/cl_wrapper.bat",
            'cl /nologo ' .
                '/c /MT /std:c++17 /EHsc ' .
                '/D "WEBVIEW_API=__declspec(dllexport)" ' .
                '/I "webview2\build\native\include" ' .
                'webview.cc ' .
                '/Fo:webview_static.obj &&' .
            'lib /nologo webview_static.obj /OUT:webview_static.lib ');
        $ret = 0;
        passthru(
            "cd {$this->sourceDir} && {$this->config->phpBinarySDKCmd} -t cl_wrapper.bat",
            $ret,
        );
        if ($ret !== 0) {
            throw new Exception("failed to build {$this->name}");
        }

        copy("{$this->sourceDir}/webview_static.lib", 'deps/lib/webview_static.lib');
        // not used yet
        // copy("{$this->sourceDir}/webview.h", 'deps/include/webview.h');
        copy(
            "{$this->sourceDir}/webview2/build/native/{$this->config->arch}/WebView2LoaderStatic.lib",
            'deps/lib/WebView2LoaderStatic.lib'
        );
    }
}
