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
