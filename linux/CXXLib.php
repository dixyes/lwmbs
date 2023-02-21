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

use PgSql\Lob;

enum CXXLib
{
    case LIBSTDCXX;
    case LIBCXX;

    public function staticLibs(bool|string $useLLD = false): string
    {
        $libs = match ($this) {
            static::LIBSTDCXX => '-lstdc++,-lgcc,-lgcc_eh',
            static::LIBCXX => '-lc++,-lc++abi',
        };

        $libs = " -Wl,-Bstatic,--start-group,$libs,--end-group,-Bdynamic ";

        // return " -Wl,-Bstatic $libs -Wl,-Bdynamic ";
        return $libs;
    }

    public function literalName(): string
    {
        return match ($this) {
            static::LIBSTDCXX => 'libstdc++',
            static::LIBCXX => 'libc++',
        };
    }
}
