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

enum CLib
{
    case GLIBC;
    case MUSL;
    case MUSL_WRAPPER;

    public function getLDInterpreter(): string
    {
        $arch = php_uname('m');

        switch ($arch) {
            case 'x86_64':
                return match ($this) {
                    static::GLIBC => 'ld-linux-x86-64.so.2',
                    static::MUSL => 'ld-musl-x86-64.so.1',
                    static::MUSL_WRAPPER => 'ld-musl-x86-64.so.1',
                };
            default:
                throw new Exception("Unsupported architecture: " . $arch);
        }
    }

    public function getCC(): string
    {
        return match ($this) {
            static::GLIBC => 'cc',
            static::MUSL => 'cc',
            static::MUSL_WRAPPER => 'musl-gcc',
        };
    }

    public function getCXX(): string
    {
        return $this->getCC() . ' -x c++';
    }

    public function getCPP(): string
    {
        return $this->getCC() . ' -E';
    }

    public function getCCEnv(bool $usedCXX = false): string
    {
        return match ($this) {
            static::GLIBC => '',
            static::MUSL => '',
            static::MUSL_WRAPPER => 'CC=musl-gcc' . ($usedCXX ? ' CXX="musl-gcc -x c++"' : ''),
        };
    }
}
