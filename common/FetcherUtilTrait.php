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

trait FetcherUtilTrait
{

    static function latestPHP(string $ver): string
    {
        $info = json_decode(static::fetch(url: "https://www.php.net/releases/index.php?json&version=$ver"), true);
        $version = $info['version'] ?? null;

        if ($version) {
            $ref = "php-$version";
        } else {
            Log::w("using master for unknown release PHP $ver");
            $ref = "master";
        }

        return $ref;
    }

    static function githubHeader(): ?string
    {
        if (!getenv('GITHUB_TOKEN')) {
            return null;
        }
        if (getenv('GITHUB_USER')) {
            $auth = base64_encode(getenv('GITHUB_USER') . ':' . getenv('GITHUB_TOKEN'));
            return "Authorization: Basic $auth";
        } else {
            $auth = getenv('GITHUB_TOKEN');
            return "Authorization: Bearer $auth";
        }
    }

    static private ?string $_7zExe = null;
    static function extractSource(string $filename, string $path): void
    {
        $ret = 0;
        if (PHP_OS_FAMILY !== 'Windows') {
            @mkdir(directory: $path, recursive: true);
            switch (Util::extname($filename)) {
                case 'xz':
                case 'txz':
                    passthru("cat $filename | xz -d | tar -x -C $path --strip-components 1",  $ret);
                    break;
                case 'gz':
                case 'tgz':
                    passthru("tar -xzf $filename -C $path --strip-components 1", $ret);
                    break;
                case 'bz2':
                    passthru("tar -xjf $filename -C $path --strip-components 1", $ret);
                    break;
                case 'zip':
                    passthru("unzip $filename -d $path", $ret);
                    break;
                case 'zstd':
                case 'zst':
                    passthru("cat $filename | zstd -d | tar -x -C $path --strip-components 1", $ret);
                    break;
                case 'tar':
                    passthru("tar -xf $filename -C $path --strip-components 1", $ret);
                    break;
                default:
                    throw new Exception("unknown archive format: $filename");
            }
        } else {
            // find 7z

            if (!static::$_7zExe) {
                $sevenzPaths = [];
                // 7z zs
                if ($sevenzPath = shell_exec("reg query \"HKLM\\SOFTWARE\\7-Zip-Zstandard\" /v Path64")) {
                    preg_match('/Path64\s+REG_SZ\s+(.*)/', $sevenzPath, $matches);
                    if ($matches) {
                        $sevenzPaths[] = preg_replace('/\\\$/', '', $matches[1]);
                    }
                }
                // Nanazip
                $sevenzPaths[] = getenv('LOCALAPPDATA') . 'Microsoft\WindowsApps';
                // 7z origin
                if ($sevenzPath = shell_exec("reg query \"HKLM\\SOFTWARE\\7-Zip\" /v Path64")) {
                    preg_match('/Path64\s+REG_SZ\s+(.*)/', $sevenzPath, $matches);
                    if ($matches) {
                        $sevenzPaths[] = preg_replace('/\\\$/', '', $matches[1]);
                    }
                }

                static::$_7zExe = Util::findCommand('7z', $sevenzPaths);
                if (!static::$_7zExe) {
                    throw new Exception('needs 7z to extract on Windows');
                }
            }

            @mkdir($path, recursive: true);
            switch (Util::extname($filename)) {
                case 'zstd':
                case 'zst':
                    if (!str_contains(static::$_7zExe, 'Zstandard') && !str_contains(static::$_7zExe, 'WindowsApps')) {
                        throw new Exception("zstd is not supported: $filename");
                    }
                case 'xz':
                case 'txz':
                case 'gz':
                case 'tgz':
                case 'bz2':
                    passthru('"' . static::$_7zExe . "\" x -so $filename | tar -f - -x -C $path --strip-components 1", $ret);
                    break;
                case 'tar':
                    passthru("tar -xf $filename -C $path --strip-components 1", $ret);
                    break;
                case 'zip':
                    passthru('"' . static::$_7zExe . "\" x $filename -o$path", $ret);
                    break;
                default:
                    throw new Exception("unknown archive format: $filename");
            }
        }
        if ($ret !== 0) {
            if (PHP_OS_FAMILY === 'Windows') {
                passthru("rmdir /s /q $path");
            } else {
                passthru("rm -r $path");
            }
            throw new Exception("failed to extract $filename source");
        }
    }

    /**
     * dirty curl download, for better https_proxy things support
     */
    static function download(string $url, string $path, string $method = 'GET', array $headers = [], array $hooks = []): void
    {
        foreach ($hooks as $hook) {
            $hook($method, $url, $headers);
        }

        $methodArg = match ($method) {
            'GET' => '',
            'HEAD' => '-I',
            default => "-X \"$method\"",
        };
        $headerArg = implode(' ', array_map(fn ($v) => '"-H' . $v . '"', $headers));

        $cmd = "curl -sfSL -o \"$path\" $methodArg $headerArg \"$url\"";
        passthru($cmd, $ret);
        if (0 !== $ret) {
            throw new Exception('failed http download');
        }
    }

    /**
     * dirty curl http request, for better https_proxy things support
     */
    static function fetch(string $url, string $method = 'GET', array $headers = [], array $hooks = []): ?string
    {
        foreach ($hooks as $hook) {
            $hook($method, $url, $headers);
        }

        $methodArg = match ($method) {
            'GET' => '',
            'HEAD' => '-I',
            default => "-X \"$method\"",
        };
        $headerArg = implode(' ', array_map(fn ($v) => '"-H' . $v . '"', $headers));

        $cmd = "curl -sfSL $methodArg $headerArg \"$url\"";
        exec($cmd, $output, $ret);
        if (0 !== $ret) {
            throw new Exception('failed http fetch');
        }
        return implode("\n", $output);
    }
}
