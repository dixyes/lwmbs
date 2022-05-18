<?php

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
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $item) {
            $target = $to . substr($item, strlen($from));
            if ($item->isDir()) {
                Log::i("mkdir $target");
                mkdir($target, recursive: true);
            } else {
                Log::i("copying $item to $target");
                copy($item, $target);
            }
        }
    }
}
