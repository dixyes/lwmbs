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

trait CommonUtilTrait
{
    use FetcherUtilTrait;

    private function __construct()
    {
    }

    public static function findCommand(string $name, array $paths = []): ?string
    {
        if (!$paths) {
            $paths = explode(PATH_SEPARATOR, getenv('PATH'));
        }
        if (PHP_OS_FAMILY === 'Windows') {
            foreach ($paths as $path) {
                foreach (['.exe', '.bat', '.cmd'] as $suffix) {
                    if (file_exists($path . DIRECTORY_SEPARATOR . $name . $suffix)) {
                        return $path . DIRECTORY_SEPARATOR . $name . $suffix;
                    }
                }
            }
            return null;
        }
        foreach ($paths as $path) {
            if (file_exists($path . DIRECTORY_SEPARATOR . $name)) {
                return $path . DIRECTORY_SEPARATOR . $name;
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

    public static function parseArgs(array $argv, array $positionalNames, array $namedKeys, ?string $example = null): array
    {
        $positional = [];
        $named = [];
        $self = array_shift($argv);
        $ret = 1;
        foreach ($argv as $arg) {
            if ($arg === '--help' || $arg === '--usage' || $arg === '-h') {
                goto help;
            }
            if (str_starts_with($arg, '-')) {
                $kv = explode('=', ltrim($arg, '-'), 2);
                if (array_key_exists($kv[0], $namedKeys)) {
                    $named[$kv[0]] = $kv[1] ?? true;
                } else {
                    Log::e("Unknown named argument: $kv[0]");
                    goto usage;
                }
            } else {
                $index = count($positional);
                if ($index >= count($positionalNames)) {
                    Log::e("Too many positional arguments");
                    goto usage;
                }
                $positional[array_keys($positionalNames)[$index]] = $arg;
            }
        }

        foreach ($positionalNames as $name => [$placeholder, $necessary]) {
            if (!array_key_exists($name, $positional)) {
                if ($necessary) {
                    Log::e("Missing positional argument: $placeholder");
                    goto usage;
                } else {
                    $positional[$name] = $positionalNames[$name][2];
                }
            }
        }

        foreach ($namedKeys as $key => [$placeholder, $necessary]) {
            if (!array_key_exists($key, $named)) {
                if ($necessary) {
                    Log::e("Missing named argument: $key");
                    goto usage;
                } else {
                    $named[$key] = $namedKeys[$key][2];
                }
            }
        }

        return [
            'self' => $self,
            'positional' => $positional,
            'named' => $named,
        ];
        help:
        $ret = 0;
        usage:
        $details = [];
        $positionalHelp = [];
        foreach ($positionalNames as $name => [$placeholder, $necessary]) {
            $positionalHelp[] = $necessary ? "<$placeholder>" : "[$placeholder]";
            if ($message = $positionalNames[$name][3] ?? false) {
                $details[] = "    $placeholder: $message";
            }
        }
        $positionalHelp = implode(' ', $positionalHelp);
        $namedHelp = [];
        foreach ($namedKeys as $key => [$placeholder, $necessary]) {
            $namedHelp[] = $necessary ? "<-$key=<$placeholder>>" : "[-$key=<$placeholder>]";
            if ($message = $namedKeys[$key][3] ?? false) {
                $details[] = "    -$key=<$placeholder>: $message";
            }
        }
        $namedHelp = implode(' ', $namedHelp);
        Log::i("Usage: $self $positionalHelp $namedHelp");
        Log::i('Arguments:');
        foreach ($details as $message) {
            Log::i($message);
        }
        if ($example) {
            Log::i("Example:");
            Log::i("    $example");
        }
        throw new Exception('bad args');
    }

    public static function gnuArch(string $arch): string
    {
        $arch = strtolower($arch);
        $ret = match ($arch) {
            'x86_64', 'x64', 'amd64' => 'x86_64',
            'arm64', 'aarch64' => 'aarch64',
            //'armv7' => 'arm',
        };
        return $ret;
    }

    public static function makeConfig(array $argv, bool $libsOnly = false): array
    {
        $namedKeys = match (PHP_OS_FAMILY) {
            'Windows', 'WINNT', 'Cygwin' => [
                'phpBinarySDKDir' => ['path to sdk', true, null, 'path to binary sdk'],
                'vsVer' => ['vs version', true, null, 'vs version, e.g. "17" for Visual Studio 2022'],
                'arch' => ['arch', false, 'x64', 'architecture, "x64" or "arm64:'], // TODO: use real host arch
            ],
            'Darwin' => [
                'cc' => ['compiler', false, null, 'C compiler'],
                'cxx' => ['compiler', false, null, 'C++ compiler'],
                'arch' => ['arch', false, php_uname('m'), 'architecture'],
            ],
            'Linux' => [
                'cc' => ['compiler', false, null, 'C compiler'],
                'cxx' => ['compiler', false, null, 'C++ compiler'],
                'arch' => ['arch', false, php_uname('m'), 'architecture'],
                'noSystem' => ['BOOL', false, false, 'donot use system static libraries'],
            ]
        };
        $namedKeys['fresh'] = ['BOOL', false, false, 'fresh build'];
        $namedKeys['bloat'] = ['BOOL', false, false, 'add all libraries into binary'];
        $namedKeys['fakeCli'] = ['BOOL', false, false, 'build with PHP_MICRO_FAKE_CLI defination'];
        if (!$libsOnly) {
            $namedKeys['allStatic'] = ['static', false, false, 'use -all-static in php build'];
        }

        $positionalNames = [
            'libraries' => ['LIBRARIES', true, null, 'select libraries, comma separated'],
        ];
        if (!$libsOnly) {
            $positionalNames['extensions'] = ['EXTENSIONS', true, null, 'select extensions, comma separated'];
        }

        $cmdArgs = Util::parseArgs(
            argv: $argv,
            positionalNames: $positionalNames,
            namedKeys: $namedKeys,
        );

        $config = Config::fromCmdArgs($cmdArgs);

        $libNames = array_filter(array_map('trim', explode(',', $cmdArgs['positional']['libraries'])));

        if (!$libsOnly) {
            $extNames = array_filter(array_map('trim', explode(',', $cmdArgs['positional']['extensions'])));
        }

        $allStatic = (bool)($cmdArgs['named']['allStatic'] ?? false);

        if ($allStatic) {
            if (false !== array_search('libffi', $libNames, true)) {
                unset($libNames[array_search('libffi', $libNames, true)]);
            }
            if (!$libsOnly && false !== array_search('ffi', $extNames, true)) {
                unset($extNames[array_search('ffi', $extNames, true)]);
            }
        }

        foreach ($libNames as $name) {
            $lib = new ("Lib$name")($config);
            $config->addLib($lib);
        }

        if (!$libsOnly) {
            foreach ($extNames as $name) {
                $ext = new Extension(name: $name, config: $config);
                $config->addExt($ext);
            }
            //check dependencies
            $config->makeExtArray();
        }

        return [$cmdArgs, $config];
    }
}
