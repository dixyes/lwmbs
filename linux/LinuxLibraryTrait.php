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

trait LinuxLibraryTrait
{
    use CommonLibraryTrait;
    use UnixLibraryTrait;

    public function prove(bool $forceBuild = false): void
    {
        //Log::i("checking cpp for {$this->name}");
        //passthru("find {$this->sourceDir} -type f -name '*.cpp'");
        //return;
        foreach ($this->staticLibs as $name) {
            if (!file_exists("lib/{$name}")) {
                Log::i("{$this->name} lib $name not found, reproving");
                goto make;
            }
        }
        foreach ($this->headers as $name) {
            if (!file_exists("include/{$name}")) {
                Log::i("{$this->name} header $name not found, reproving");
                goto make;
            }
        }
        Log::i("{$this->name} already proven");
        return;
        make:
        if ($forceBuild || php_uname('m') !== $this->config->arch ) {
            goto build;
        }

        $staticLibPathes = Util::findStaticLibs($this->staticLibs);
        $headerPathes = Util::findHeaders($this->headers);
        if (!$staticLibPathes || !$headerPathes) {
            build:
            $this->build();
        } else {
            if ($this->config->libc === CLib::MUSL_WRAPPER) {
                Log::w("libc type may not match, this may cause strange symbol missing");
            }
            $this->copyExist($staticLibPathes, $headerPathes);
        }
        foreach ($this->pkgconfs as $name => $_) {
            Util::fixPkgConfig("lib/pkgconfig/$name");
        }

        Log::i("{$this->name} proven");
    }

    protected function copyExist(array $staticLibPathes, array $headerPathes): void
    {
        if (!$staticLibPathes || !$headerPathes) {
            throw new Exception('??? staticLibPathes or headerPathes is null');
        }
        Log::i("using system {$this->name}");
        foreach ($staticLibPathes as [$path, $staticLib]) {
            @mkdir('lib/' . dirname($staticLib), recursive: true);
            Log::i("copy $path/$staticLib to lib/$staticLib");
            copy("$path/$staticLib", "lib/" . $staticLib);
        }
        foreach ($headerPathes as [$path, $header]) {
            @mkdir('include/' . dirname($header), recursive: true);
            Log::i("copy $path/$header to include/$header");
            if (is_dir("$path/$header")) {
                Util::copyDir("$path/$header", "include/$header");
            } else {
                copy("$path/$header", "include/$header");
            }
        }
        $this->makeFakePkgconfs();
    }
    protected function makeFakePkgconfs()
    {
        foreach ($this->pkgconfs as $name => $content) {
            file_put_contents("lib/pkgconfig/$name", 'prefix=' . realpath('') . "\n" . $content);
        }
    }
}
