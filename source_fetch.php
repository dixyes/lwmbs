<?php

function extract_source(string $name, string $filename): void
{
    logi("extracting $name source");
    @mkdir("src/$name");
    switch (extname($filename)) {
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
            loge("unknown archive format: " . $filename);
            exit(1);
    }
    if ($ret !== 0) {
        loge("failed to extract $name source");
        exit(1);
    }
}

function fetch_sources(array $sources, callable $filter)
{
    $auth = base64_encode(getenv('GITHUB_USER') . ':' . getenv('GITHUB_TOKEN'));
    $github_context = stream_context_create([
        "http" => [
            'proxy' => preg_replace('|^http://|','tcp://',getenv('https_proxy')),
            "header" => "Authorization: Basic $auth\r\nUser-Agent: lwmbs/0\r\n"
        ]
    ]);
    $proxy_context = stream_context_create([
        "http" => [
            'proxy' => preg_replace('|^http://|','tcp://',getenv('https_proxy')),
            "header" => "User-Agent: lwmbs/0\r\n"
        ]
    ]);
    $sources = array_filter($sources, $filter, ARRAY_FILTER_USE_BOTH);
    foreach ($sources['libs'] as $name => $source) {
        switch ($source['type']) {
            case 'ghrel':
                $data = json_decode(file_get_contents("https://api.github.com/repos/{$source['repo']}/releases", context: $github_context), true);
                $url = null;
                foreach ($data[0]['assets'] as $asset) {
                    if (preg_match('|' . $source['match'] . '|', $asset['name'])) {
                        $url = $asset['browser_download_url'];
                        break;
                    }
                }
                if (!$url) {
                    loge("failed to find $name source");
                    exit(1);
                }
                $filename = basename($url);
                goto download;
            case 'url':
                $url = $source['url'];
                $filename = $source['name'];
                download:
                if (file_exists($filename)) {
                    logi("$name source already exists");
                    break;
                }
                logi("downloading $name source");
                file_put_contents($filename, file_get_contents($url,context:$proxy_context));
                extract_source($name, $filename);
                break;
            case 'git':
                if (file_exists('src/' . $name)) {
                    logi("$name source already exists");
                    break;
                }
                logi("cloning $name source");
                passthru('git clone ' . $source['url'] . ' src/' . $name, $ret);
                if ($ret !== 0) {
                    loge("failed to clone $name source");
                    exit(1);
                }
                break;
            default:
                loge("unknown source type: " . $source['type']);
                exit(1);
        }
    }
}
