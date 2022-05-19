<?php

enum LogType
{
    case ERROR;
    case WARNING;
    case INFO;
    public function shortName(): string
    {
        switch ($this) {
            case static::ERROR:
                return 'E';
            
            // no break
            case static::WARNING:
                return 'W';
            case static::INFO:
                return 'I';
        }
    }
    public function color(): string
    {
        switch ($this) {
            case static::ERROR:
                return "\033[31m";
            case static::WARNING:
                return "\033[33m";
            case static::INFO:
                return "\033[32m";
        }
    }
}
