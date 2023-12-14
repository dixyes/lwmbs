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
    use MacOSLibraryTrait;
    protected string $name = 'webview';
    protected array $staticLibs = [
        'webview_static.a',
    ];
    protected array $headers = [
    ];
    protected array $depNames = [
    ];

    protected function build(): void
    {
        Log::i("building {$this->name}");

        // build webview
        $ret = 0;
        passthru(
            $this->config->setX . ' && ' .
                "cd {$this->sourceDir} && " .
                'rm -rf build && ' .
                'mkdir -p build && ' .
                'cd build && ' .
                'clang++ -c ../webview.cc -std=c++11 -DWEBVIEW_SHARED -o webview.o && ' .
                'ar rcs webview_static.a webview.o', 
            $ret,
        );
        if ($ret !== 0) {
            throw new Exception("failed to build {$this->name}");
        }

        copy("{$this->sourceDir}/build/webview_static.a", 'lib/webview_static.a');
    }

    public function getFrameworks(): array
    {
        return ["WebKit"];
    }

    public function useCPP(): bool
    {
        return true;
    }
}
