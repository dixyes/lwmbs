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
require __DIR__ . '/common/CommonUtilTrait.php';

class Util
{
    use CommonUtilTrait;
}

function extractSource(string $name, string $filename): void
{
    Log::i("extracting $name source");
    $ret = 0;
    if (PHP_OS_FAMILY !== 'Windows') {
        @mkdir(directory: "src/$name", recursive: true);
        switch (Util::extname($filename)) {
            case 'xz':
            case 'txz':
                passthru("cat $filename | xz -d | tar -x -C src/$name --strip-components 1",  $ret);
                break;
            case 'gz':
            case 'tgz':
                passthru("tar -xzf $filename -C src/$name --strip-components 1", $ret);
                break;
            case 'bz2':
                passthru("tar -xjf $filename -C src/$name --strip-components 1", $ret);
                break;
            case 'zip':
                passthru("unzip $filename -d src/$name", $ret);
                break;
            // case 'zstd':
            // case 'zst':
            //     passthru('cat ' . $filename . ' | zstd -d | tar -x -C src/' . $name . ' --strip-components 1', $ret);
            //     break;
            case 'tar':
                passthru("tar -xf $filename -C src/$name --strip-components 1", $ret);
                break;
            default:
                throw new Exception("unknown archive format: " . $filename);
        }
    } else {
        // find 7z
        $_7zExe = Util::findCommand('7z', [
            'C:\Program Files\7-Zip-Zstandard',
            'C:\Program Files (x86)\7-Zip-Zstandard',
            'C:\Program Files\7-Zip',
            'C:\Program Files (x86)\7-Zip',
        ]);
        if (!$_7zExe) {
            throw new Exception('windows needs 7z to unpack');
        }
        @mkdir("src/$name", recursive: true);
        switch (Util::extname($filename)) {
            case 'zstd':
            case 'zst':
                if (!str_contains($_7zExe, 'Zstandard')) {
                    throw new Exception("zstd is not supported: $filename");
                }
            case 'xz':
            case 'txz':
            case 'gz':
            case 'tgz':
            case 'bz2':
                passthru("\"$_7zExe\" x -so $filename | tar -f - -x -C src/$name --strip-components 1", $ret);
                break;
            case 'tar':
                passthru("tar -xf $filename -C src/$name --strip-components 1", $ret);
                break;
            case 'zip':
                passthru("\"$_7zExe\" x $filename -osrc/$name", $ret);
                break;
            default:
                throw new Exception("unknown archive format: $filename");
        }
    }
    if ($ret !== 0) {
        if (PHP_OS_FAMILY === 'Windows') {
            passthru("rmdir /s /q src/$name");
        } else {
            passthru("rm -r src/$name");
        }
        throw new Exception("failed to extract $name source");
    }
}

function setupGithubToken(string &$method, string &$url, array &$headers): void
{
    if (!getenv('GITHUB_TOKEN')) {
        return;
    }
    if (getenv('GITHUB_USER')) {
        $auth = base64_encode(getenv('GITHUB_USER') . ':' . getenv('GITHUB_TOKEN'));
        $headers[] = "Authorization: Basic $auth";
        Log::i("using basic github token for $method $url");
    } else {
        $auth = getenv('GITHUB_TOKEN');
        $headers[] = "Authorization: Bearer $auth";
        Log::i("using bearer github token for $method $url");
    }
}

function download(string $url, string $path, string $method = 'GET', array $headers = [], array $hooks = []): void
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

function fetch(string $url, string $method = 'GET', array $headers = [], array $hooks = []): ?string
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

function getLatestGithubTarball(string $name, array $source): array
{
    Log::i("finding $name source from github releases tarball");
    $data = json_decode(fetch(
        url:"https://api.github.com/repos/{$source['repo']}/releases",
        hooks: [ 'setupGithubToken' ],
    ), true);
    $url = $data[0]['tarball_url'];
    if (!$url) {
        throw new Exception("failed to find $name source");
    }
    $headers = fetch(
        url: $url,
        method: 'HEAD',
        hooks: [ 'setupGithubToken' ],
    );
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
    $data = json_decode(fetch(
        url: "https://api.github.com/repos/{$source['repo']}/releases",
        hooks: [ 'setupGithubToken' ],
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
    $page = fetch($source['url']);
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
                    download(url: $url, path: "downloads/$filename");
                } else {
                    Log::i("$name source already exists");
                }
                extractSource($name, "downloads/$filename");
                break;
            case 'git':
                if ($source['path'] ?? null) {
                    $path = "src/{$source['path']}";
                } else {
                    $path = "src/{$name}";
                }
                if (file_exists($path)) {
                    Log::i("$name source already exists");
                    break;
                }
                Log::i("cloning $name source");
                passthru(
                    "git clone --branch \"{$source['rev']}\" " . ($shallowClone ? '--depth 1 --single-branch' : '') . " --recursive \"{$source['url']}\" \"{$path}\"",
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

function patch(string $majDotMin)
{
    Log::i('patching php');
    $majMin = implode('', explode('.', $majDotMin));
    $ret = 0;
    passthru(
        'cd src/php-src && ' .
            'git checkout HEAD .',
        $ret
    );
    if ($ret != 0) {
        throw new Exception("failed to reset php");
    }

    $patches = [];
    $serial = ['80', '81', '82'];
    foreach ([
        'static_opcache',
        'static_extensions_win32',
        'cli_checks',
        'disable_huge_page',
        'vcruntime140',
        'win32',
        'zend_stream',
    ] as $patchName) {
        if (file_exists("src/php-src/sapi/micro/patches/{$patchName}.patch")) {
            $patches[]="sapi/micro/patches/{$patchName}.patch";
            continue;
        }
        for ($i = array_search($majMin, $serial, true); $i >= 0; $i--) {
            $tryMajMin = $serial[$i];
            if (!file_exists("src/php-src/sapi/micro/patches/{$patchName}_{$tryMajMin}.patch")) {
                continue;
            }
            $patches[] = "sapi/micro/patches/{$patchName}_{$tryMajMin}.patch";
            continue 2;
        }
        throw new Exception("failed finding {$patchName}");
    }

    $patchesStr = str_replace('/', DIRECTORY_SEPARATOR, implode(' ', $patches));

    $ret = 0;
    passthru(
        'cd src/php-src && ' .
            (PHP_OS_FAMILY === 'Windows' ? 'type' : 'cat') . ' ' . $patchesStr . ' | patch -p1',
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
    if (PHP_OS_FAMILY === 'Windows') {
        passthru(
            'cd src/php-src/ext && ' .
                'mklink /D swow swow-src\ext',
            $ret
        );
    } else {
        passthru(
            'cd src/php-src/ext && ' .
                'ln -s swow-src/ext swow ',
            $ret
        );
    }
    if ($ret != 0) {
        throw new Exception("failed to link swow");
    }
}

function latestPHP(string $majMin){
    $info = json_decode(fetch(url: "https://www.php.net/releases/index.php?json&version=$majMin"), true);
    $version = $info['version'];

    return [
        'type'=> 'git',
        'path'=> 'php-src',
        'rev'=> "php-$version",
        'url'=> 'https://github.com/php/php-src',
    ];
}

function mian($argv): int
{
    Util::setErrorHandler();

    $cmdArgs = Util::parseArgs(
        argv: $argv,
        positionalNames: [
            'libraries' => ['LIBRARIES', false, '', 'libraries used, comma spearated'],
            'extensions' => ['EXTENSIONS', false, '', 'extensions used, comma spearated'],
        ],
        namedKeys: [
            'hash' => ['BOOL', false, false, 'hash only'],
            'shallowClone' => ['BOOL', false, false, 'use shallow clone'],
            'openssl11' => ['BOOL', false, false, 'use openssl 1.1'],
            'srcFile' => ['SRCFILE', false, __DIR__ . DIRECTORY_SEPARATOR . 'src.json', 'src.json path'],
            'phpVer' => ['VERSION', false, '8.1', 'php version in major.minor format like 8.1'],
        ],
    );

    $majMin = $cmdArgs['named']['phpVer'];

    preg_match('/^\d+\.\d+$/', $majMin, $matches);
    if (!$matches) {
        Log::e("bad version arg: {$majMin}\n");
        return 1;
    }

    if ($cmdArgs['named']['hash']) {
        Log::$outFd = STDERR;
    }

    $openssl11 = (bool)$cmdArgs['named']['openssl11'];

    $data = json_decode(file_get_contents($cmdArgs['named']['srcFile']), true);
    if ($openssl11) {
        Log::i('using openssl 1.1');
        $data['src']['openssl']['regex'] = '/href="(?<file>openssl-(?<version>1.[^"]+)\.tar\.gz)\"/';
    }

    $chosen = [
        'php',
        'micro',
    ];
    $libraries = array_map('trim', array_filter(explode(',', $cmdArgs['positional']['libraries'])));
    if ($libraries) {
        foreach ($libraries as $lib) {
            $srcName = $data['lib'][$lib];
            $chosen[] = $srcName;
        }
    } else {
        $chosen = [...$chosen, ...array_values($data['lib'])];
    }
    $extensions = array_map('trim', array_filter(explode(',', $cmdArgs['positional']['extensions'])));
    if ($extensions) {
        foreach ($extensions as $lib) {
            $srcName = $data['ext'][$lib];
            $chosen[] = $srcName;
        }
    } else {
        $chosen = [...$chosen, ...array_values($data['ext'])];
    }
    $chosen = array_unique($chosen);
    $filter = fn($_, $name) => in_array($name, $chosen, true);

    if ($cmdArgs['named']['hash']) {
        $files = [];
        foreach (array_filter($data['src'], $filter, ARRAY_FILTER_USE_BOTH) as $name => $source) {
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
    $shallowClone = (bool)$cmdArgs['named']['shallowClone'];
    Util::setErrorHandler();
    @mkdir('downloads');
    // TODO:implement filter
    // download php first
    fetchSources([
        'src' => [
            'php' => latestPHP($majMin),
        ]
    ], fn ($x) => true, $shallowClone);
    // download all sources
    fetchSources($data, $filter, $shallowClone);
    
    patch($majMin);

    if (!$openssl11 && $majMin === '8.0') {
        Log::i('patching php for openssl 3');
        $openssl_c = file_get_contents('src/php-src/ext/openssl/openssl.c');
        $openssl_c = preg_replace('/REGISTER_LONG_CONSTANT\s*\(\s*"OPENSSL_SSLV23_PADDING"\s*.+;/', '', $openssl_c);
        file_put_contents('src/php-src/ext/openssl/openssl.c', $openssl_c);
    }
    if (!file_exists('src/php-src/ext/swow')) {
        linkSwow();
    }
    Log::i('done');

    return 0;
}

exit(mian($argv));
