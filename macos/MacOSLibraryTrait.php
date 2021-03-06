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

trait MacOSLibraryTrait
{
    use CommonLibraryTrait;
    use UnixLibraryTrait;

    public function prove(bool $forceBuild = false, bool $fresh = false): void
    {
        if ($fresh) {
            goto make;
        }

        foreach ($this->staticLibs as $name) {
            if (!file_exists("lib/{$name}")) {
                goto make;
            }
        }
        foreach ($this->headers as $name) {
            if (!file_exists("include/{$name}")) {
                goto make;
            }
        }
        Log::i("{$this->name} already proven");
        return;
        make:

        $this->build();

        Log::i("{$this->name} proven");
    }

    
    public function getFrameworks(): array
    {
        return $this->frameworks;
    }
}
