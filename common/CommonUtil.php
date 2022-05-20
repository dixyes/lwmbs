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

trait CommonUtil
{
    private function __construct()
    {
    }

    public static function findCommand(string $name): ?string
    {
        $paths = getenv('PATH');
        foreach (explode(PATH_SEPARATOR, $paths) as $path) {
            if (file_exists($path . DIRECTORY_SEPARATOR . $name)) {
                return $path . $name;
            }
        }
        return null;
    }

    /**
     * @return array<string>
     */
    public static function lackingCommands(array $commands): array
    {
        $ret = [];
        foreach ($commands as $command) {
            if (!static::findCommand($command)) {
                $ret[] = $command;
            }
        }
        return $ret;
    }

    public static function extname(string $fn): string
    {
        $parts = explode('.', basename($fn));
        if (count($parts) < 2) {
            return '';
        } else {
            return array_pop($parts);
        }
    }

    public static function setErrorHandler(): void
    {
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            if (!($errno & error_reporting()) || $errno === E_STRICT) {
                return;
            }
            throw new ErrorException(
                message: $errstr,
                code: 0,
                severity: $errno,
                filename: $errfile,
                line: $errline
            );
        });
    }

    public static function copyDir(string $from, string $to)
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $item) {
            /**
             * @var SplFileInfo $item
             */
            $target = $to . substr($item->getPathname(), strlen($from));
            if ($item->isDir()) {
                Log::i("mkdir $target");
                mkdir($target, recursive: true);
            } else {
                Log::i("copying $item to $target");
                @mkdir(dirname($target), recursive: true);
                copy($item->getPathname(), $target);
            }
        }
    }
}
