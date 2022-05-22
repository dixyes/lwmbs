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

final class Util
{
    use CommonUtil;
    use UnixUtil;

    public static function getArchCFlags(string $arch):string {
        return match ($arch) {
            'x86_64' => '--target=x86_64-apple-darwin',
            'arm64' => '--target=arm64-apple-darwin',
            default => throw new Exception('unsupported arch: ' . $arch),
        };
    }

    public static function getCpuCount(): int
    {
        exec('sysctl -n hw.ncpu', $output, $ret);
        if ($ret !== 0) {
            throw new Exception('Failed to get cpu count');
        }

        return (int) $output[0];
    }

}
