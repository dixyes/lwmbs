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

function download(string $url, array $headers = [], bool $useGithubToken = false): string
{
    if ($useGithubToken && getenv('GITHUB_TOKEN')) {
        $auth = base64_encode(getenv('GITHUB_USER') . ':' . getenv('GITHUB_TOKEN'));
        $headers[] = "Authorization: Basic $auth";
    }

    return fetch(url: $url, headers: $headers);
}

function fetch(string $url, string $method = 'GET', array $headers = []): string
{
    $methodArg = match ($method) {
        'GET' => '',
        'HEAD' => '-I',
        default => "-X $method",
    };
    $headerArg = implode(' ', array_map(fn ($v) => "-H\"$v\"", $headers));
    return `curl -sfSL $methodArg $headerArg $url`;
}

function getLatestGithubTarball(string $name, array $source): array
{
    Log::i("finding $name source from github releases tarball");
    $data = json_decode(download(
        "https://api.github.com/repos/{$source['repo']}/releases",
        useGithubToken: true,
    ), true);
    $url = $data[0]['tarball_url'];
    if (!$url) {
        throw new Exception("failed to find $name source");
    }
    $headers = fetch($url, method: 'HEAD');
    preg_match('/^content-disposition:\s+attachment;\s*filename=("{0,1})(?<filename>.+\.tar\.gz)\1/im', $headers, $matches);
    if ($matches) {
        $filename = $matches['filename'];
    } else {
        $filename = "$name-{$data['tag_name']}.tar.gz";
    }

    return [$url, $filename];
}

function getLatestGithubRelease(string $name, array $source): array
{
    Log::i("finding $name source from github releases assests");
    $data = json_decode(download(
        "https://api.github.com/repos/{$source['repo']}/releases",
        useGithubToken: true,
    ), true);
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

    return [$url, $filename];
}

function getFromFileList(string $name, array $source): array
{
    Log::i("finding $name source from file list");
    $page = download($source['url']);
    preg_match_all($source['regex'], $page, $matches);
    if (!$matches) {
        throw new Exception("Failed to get $name version");
    }
    $versions = [];
    foreach ($matches['version'] as $i => $version) {
        $lowerVersion = strtolower($version);
        foreach ([
            'alpha',
            'beta',
            'rc',
            'pre',
            'nightly',
            'snapshot',
            'dev',
        ] as $betaVersion) {
            if (str_contains($lowerVersion, $betaVersion)) {
                continue 2;
            }
        }
        $versions[$version] = $matches['file'][$i];
    }
    uksort($versions, 'version_compare');

    return [$source['url'] . end($versions), end($versions)];
}

function fetchSources(array $data, callable $filter, bool $shallowClone = false)
{
    $sources = array_filter($data['src'], $filter, ARRAY_FILTER_USE_BOTH);

    foreach ($sources as $name => $source) {
        if (is_dir("src/$name")) {
            Log::i("$name source already extracted");
            continue;
        }
        switch ($source['type']) {
            case 'ghtar':
                [$url, $filename] = getLatestGithubTarball($name, $source);
                goto download;
            case 'ghrel':
                [$url, $filename] = getLatestGithubRelease($name, $source);
                goto download;
            case 'filelist':
                [$url, $filename] = getFromFileList($name, $source);
                goto download;
            case 'url':
                $url = $source['url'];
                $filename = $source['name'];
                download:
                if (!file_exists("downloads/$filename")) {
                    Log::i("downloading $name source from $url");
                    file_put_contents("downloads/$filename", download($url));
                } else {
                    Log::i("$name source already exists");
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
                    "git clone --branch '{$source['rev']}' " . ($shallowClone ? '--depth 1 --single-branch' : '') . " --recursive '{$source['url']}' 'src/{$source['path']}'",
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
        'cd src/php-src && ' .
            'git checkout HEAD . && ' .
            'git apply sapi/micro/patches/disable_huge_page.patch && ' .
            'git apply sapi/micro/patches/cli_checks_81.patch && ' .
            $ret
    );
    if ($ret != 0) {
        throw new Exception("failed to patch php");
    }
}

function linkSwow()
{
    Log::i('linking swow');
    $ret = 0;
    passthru(
        'cd src/php-src/ext && ' .
            'ln -s swow-src/ext swow ',
        $ret
    );
    if ($ret != 0) {
        throw new Exception("failed to patch php");
    }
}

function buildconf()
{
    Log::i('make buildconf');
    $ret = 0;
    passthru(
        'cd src/php-src && ' .
            './buildconf --force',
        $ret
    );
    if ($ret != 0) {
        throw new Exception("failed to patch php");
    }
}

function mian($argv): int
{
    if (count($argv) < 2) {
        Log::e("usage: php {$argv[0]} <src-file> [--hash] [--shallow-clone]\n");
        return 1;
    }
    $data = json_decode(file_get_contents($argv[1]), true);
    if (in_array('--hash', $argv)) {
        Log::$outFd = STDERR;
        $files = [];
        foreach ($data['src'] as $name => $source) {
            switch ($source['type']) {
                case 'git':
                    continue 2;
                case 'ghtar':
                    [$_, $filename] = getLatestGithubTarball($name, $source);
                    break;
                case 'ghrel':
                    [$_, $filename] = getLatestGithubRelease($name, $source);
                    break;
                case 'filelist':
                    [$url, $filename] = getFromFileList($name, $source);
                    break;
                case 'url':
                    $filename = $source['name'];
                    break;
                default:
                    throw new Exception("unknown source type: " . $source['type']);
            }
            Log::i("found $name source: $filename");
            $files[] = $filename;
        }
        echo hash('sha256', implode('|', $files)) . "\n";
        return 0;
    }
    $shallowClone = false;
    if (in_array('--shallow-clone', $argv, true)) {
        $shallowClone = true;
    }
    Util::setErrorHandler();
    @mkdir('downloads');
    // TODO:implement filter
    // download all sources
    fetchSources($data, fn ($x) => true, $shallowClone);
    patch();
    linkSwow();
    buildconf();
    Log::i('done');

    return 0;
}

exit(mian($argv));
