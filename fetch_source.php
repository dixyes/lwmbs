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

function extractSource(string $name, string $filename): void
{
    Log::i("extracting $name source");
    @mkdir("src/$name", recursive: true);
    $ret = 0;
    switch (Util::extname($filename)) {
        case 'xz':
        case 'txz':
            passthru('cat ' . $filename . ' | xz -d | tar -x -C src/' . $name . ' --strip-components 1',  $ret);
            break;
        case 'gz':
        case 'tgz':
            passthru('tar -xzf ' . $filename . ' -C src/' . $name . ' --strip-components 1',  $ret);
            break;
        case 'bz2':
            passthru('tar -xjf ' . $filename . ' -C src/' . $name . ' --strip-components 1', $ret);
            break;
        case 'zip':
            passthru('unzip ' . $filename . ' -d src/' . $name, $ret);
            break;
        case 'zstd':
        case 'zst':
            passthru('cat ' . $filename . ' | zstd -d | tar -x -C src/' . $name . ' --strip-components 1', $ret);
            break;
        case 'tar':
            passthru('tar -xf ' . $filename . ' -C src/' . $name . ' --strip-components 1', $ret);
            break;
        default:
            throw new Exception("unknown archive format: " . $filename);
    }
    if ($ret !== 0) {
        passthru("rm -r src/$name");
        throw new Exception("failed to extract $name source");
    }
}

function fetchSources(string $srcFile, callable $filter)
{
    $sources = json_decode(file_get_contents($srcFile), true);
    $sources = array_filter($sources, $filter, ARRAY_FILTER_USE_BOTH);

    foreach ($sources['libs'] as $name => $source) {
        $auth = base64_encode(getenv('GITHUB_USER') . ':' . getenv('GITHUB_TOKEN'));
        $github_context = stream_context_create([
            "http" => [
                'proxy' => preg_replace('|^http://|', 'tcp://', getenv('https_proxy')),
                "header" => "Authorization: Basic $auth\r\nAccept: */*\r\nUser-Agent: lwmbs/0\r\n",
                'request_fulluri' => true,
            ]
        ]);
        $proxy_context = stream_context_create([
            "http" => [
                'proxy' => preg_replace('|^http://|', 'tcp://', getenv('https_proxy')),
                "header" => "User-Agent: lwmbs/0\r\nAccept: */*\r\n",
                'request_fulluri' => true,
            ]
        ]);
        switch ($source['type']) {
            case 'ghrel':
                Log::i("finding $name source from github releases");
                $data = json_decode(file_get_contents("https://api.github.com/repos/{$source['repo']}/releases", context: $github_context), true);
                $url = null;
                foreach ($data[0]['assets'] as $asset) {
                    if (preg_match('|' . $source['match'] . '|', $asset['name'])) {
                        $url = $asset['browser_download_url'];
                        break;
                    }
                }
                if (!$url) {
                    throw new Exception("failed to find $name source");
                }
                $filename = basename($url);
                goto download;
            case 'url':
                $url = $source['url'];
                $filename = $source['name'];
                download:
                if (!file_exists("downloads/$filename")) {
                    Log::i("downloading $name source from $url");
                    file_put_contents("downloads/$filename", file_get_contents($url, context: $proxy_context));
                } else {
                    Log::i("$name source already exists");
                }
                if (is_dir("src/$name")) {
                    Log::i("$name source already extracted");
                    break;
                }
                extractSource($name, "downloads/$filename");
                break;
            case 'git':
                if (file_exists("src/{$source['path']}")) {
                    Log::i("$name source already exists");
                    break;
                }
                Log::i("cloning $name source");
                passthru(
                    "git clone --branch '{$source['rev']}' '{$source['url']}' 'src/{$source['path']}'",
                    $ret
                );
                if ($ret !== 0) {
                    throw new Exception("failed to clone $name source");
                }
                break;
            default:
                throw new Exception("unknown source type: " . $source['type']);
        }
    }
}

function patch()
{
    Log::i('patching php');
    $ret = 0;
    passthru(
        'cd src/php-src && '.
        'git checkout HEAD . && '.
        'git apply sapi/micro/patches/disable_huge_page.patch && '.
        'git apply sapi/micro/patches/cli_checks_81.patch && '.
        './buildconf --force',
        $ret
    );
    if ($ret != 0) {
        throw new Exception("failed to patch php");
    }
}

function mian($argv): int
{
    Util::setErrorHandler();
    @mkdir('downloads');
    // TODO:implement filter
    // download all sources
    fetchSources($argv[1], fn ($x) => true);
    patch();
    Log::i('done');

    return 0;
}

exit(mian($argv));
