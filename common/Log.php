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

final class Log
{
    public static $outFd = STDOUT;
    public static function log(
        LogType $type,
        ?string $tag,
        array $args,
        int $level = 0,
    ) {
        $trace = debug_backtrace(limit: 1 + $level)[$level];
        $filename = basename($trace['file']);
        $line = $trace['line'];
        $tag = $tag ?? "$filename:$line";
        $msg = sprintf(...$args);

        fprintf(static::$outFd, "{$type->color()}[{$type->shortName()}:$tag]\033[0;1m $msg\033[0m\n");
    }
    public static function i(...$args)
    {
        static::log(type: LogType::INFO, tag: null, level: 1, args: $args);
    }
    public static function w(...$args)
    {
        static::log(type: LogType::WARNING, tag: null, level: 1, args: $args);
    }
    public static function e(...$args)
    {
        static::log(type: LogType::ERROR, tag: null, level: 1, args: $args);
    }
}
