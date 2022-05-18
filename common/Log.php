<?php

final class Log
{
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

        echo "{$type->color()}[{$type->shortName()}:$tag]\033[0;1m $msg\033[0m\n";
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
