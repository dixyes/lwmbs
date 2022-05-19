#!php
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

require __DIR__ . '/common/Log.php';
require __DIR__ . '/common/LogType.php';
require __DIR__ . '/common/CommonUtil.php';

class Util
{
    use CommonUtil;
}

function genData(): array
{
    $ret = [
        'src' => [
            // things need to be mannually updated
            'zlib' => [
                "type" => "url",
                "url" => "https://zlib.net/zlib-1.2.12.tar.gz",
                "name" => "zlib-1.2.12.tar.gz",
            ],
            'bzip2' => [
                "type" => "url",
                "url" => "https://sourceware.org/pub/bzip2/bzip2-1.0.8.tar.gz",
                "name" => "bzip2-1.0.8.tar.gz",
            ],
            "xz" => [
                "type" => "url",
                "url" => "https://tukaani.org/xz/xz-5.2.5.tar.xz",
                "name" => "xz-5.2.5.tar.xz",
            ],
            // github release files
            "nghttp2" => [
                "type" => "ghrel",
                "repo" => "nghttp2/nghttp2",
                "match" => "nghttp2.+\\.tar\\.xz",
            ],
            "onig" => [
                "type" => "ghrel",
                "repo" => "kkos/oniguruma",
                "match" => "onig-.+\\.tar\\.gz",
            ],
            "libssh2" => [
                "type" => "ghrel",
                "repo" => "libssh2/libssh2",
                "match" => "libssh2.+\\.tar\\.gz",
            ],
            "libffi" => [
                "type" => "ghrel",
                "repo" => "libffi/libffi",
                "match" => "libffi.+\\.tar\\.gz",
            ],
            "libzip" => [
                "type" => "ghrel",
                "repo" => "nih-at/libzip",
                "match" => "libzip.+\\.tar\\.xz",
            ],
            "curl" => [
                "type" => "ghrel",
                "repo" => "curl/curl",
                "match" => "curl.+\\.tar\\.xz",
            ],
            // git instant clone, needs to be mannually updated
            "php" => [
                "type" => "git",
                "path" => "php-src",
                "rev" => "php-8.1.6",
                "url" => "https://github.com/php/php-src",
            ],
            "swoole" => [
                "type" => "git",
                "path" => "php-src/ext/swoole",
                "rev" => "master",
                "url" => "https://github.com/swoole/swoole-src",
            ],
            "swow" => [
                "type" => "git",
                "path" => "php-src/ext/swow-src",
                "rev" => "ci",
                "url" => "https://github.com/swow/swow",
            ],
            "parallel" => [
                "type" => "git",
                "path" => "php-src/ext/parallel",
                "rev" => "dixyes-dev",
                "url" => "https://github.com/dixyes/parallel",
            ],
            "redis" => [
                "type" => "git",
                "path" => "php-src/ext/redis",
                "rev" => "5.3.7",
                "url" => "https://github.com/phpredis/phpredis",
            ],
            "micro" => [
                "type" => "git",
                "path" => "php-src/sapi/micro",
                "rev" => "master",
                "url" => "https://github.com/dixyes/phpmicro",
            ],
        ],
    ];

    $fileList = [
        'libiconv' => [
            'url'=> 'https://ftp.gnu.org/pub/gnu/libiconv/',
            'regex' => '/href="(?<file>libiconv-(?<version>.+)\.tar\.gz)"/',
        ],
        'openssl' => [
            'url'=>'https://www.openssl.org/source/',
            'regex' => '/href="(?<file>openssl-(?<version>.+)\.tar\.gz)"/',
        ],
    ];

    foreach($fileList as $name => $info) {
        $proxy_context = stream_context_create([
            "http" => [
                'proxy' => preg_replace('|^http://|', 'tcp://', getenv('https_proxy')),
                "header" => "User-Agent: lwmbs/0\r\nAccept: */*\r\n",
                'request_fulluri' => true,
            ]
        ]);
        $page = file_get_contents($info['url'], false, $proxy_context);
        preg_match_all($info['regex'], $page, $matches);
        if (!$matches) {
            throw new Exception("Failed to get $name version");
        }
        $vesions = [];
        foreach($matches['version'] as $i => $version) {
            $versions[$version] = $matches['file'][$i];
        }
        uksort($versions, 'version_compare');
        $ret['src'][$name] = [
            'type' => 'url',
            'url' => $info['url'] . end($versions),
            'name' => end($versions),
        ];
    }

    return $ret;
}


function mian($argv): int
{
    if (!isset($argv[1])) {
        Log::e('Usage: php ' . $argv[0] . ' <path to json>');
        exit(1);
    }
    Util::setErrorHandler();
    $data = genData();
    file_put_contents($argv[1], json_encode($data));
    return 0;
}

exit(mian($argv));
