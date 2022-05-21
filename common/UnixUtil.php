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

trait UnixUtil {
    public static function checkCCFlag(string $flag):string {
        $ret = 0;
        exec("echo | cc -E -x c - $flag", $dummy, $ret);
        if ($ret != 0) {
            return "";
        }
        return $flag;   
    }
    
    public static function checkCCFlags(array $flags):array {
        return array_filter($flags, fn ($flag) => static::checkCCFlag($flag));
    }

    public static function libtoolCCFlags(array $flags):string {
        return implode(' ', array_map(fn($x)=> "-Xcompiler $x", $flags));
    }
}