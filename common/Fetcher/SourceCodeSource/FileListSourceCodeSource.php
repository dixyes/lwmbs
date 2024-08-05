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

class FileListSourceCodeSource extends SourceCodeSource
{
    
    protected function validate()
    {
        if (!isset($this->config['url'])) {
            throw new Exception("key 'url' is required for FileListSourceCodeSource");
        }
        if (!isset($this->config['match'])) {
            throw new Exception("key 'match' is required for FileListSourceCodeSource");
        }
    }

    public readonly string $url;
    public readonly string $fileName;
    private function latestUrl(): string
    {
        if (isset($this->url)) {
            return $this->url;
        }

        Log::i("finding {$this->name} source from file list {$this->config['url']}");

        $page = Util::fetch($this->config['url']);
        preg_match_all($this->config['match'], $page, $matches);
        if (!$matches) {
            throw new Exception("Failed to get {$this->name} version");
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
            if (!isset($versions[$version])) {
                $versions[$version] = [];
            }
            if (isset($matches['file'][$i])) {
                $versions[$version]['file'] = $matches['file'][$i];
            }
            if (isset($matches['url'][$i])) {
                $versions[$version]['url'] = $matches['url'][$i];
            }
        }
        uksort($versions, 'version_compare');

        $version = end($versions);
        if (isset($version['url'])) {
            $this->url = $version['url'];
            $parsed = parse_url($this->url);
            $this->fileName = basename($parsed['path']);
        } else if (str_starts_with($version['file'], 'http') || str_starts_with($version['file'], 'ftp')) {
            $this->url = $version['file'];
            $parsed = parse_url($this->url);
            $this->fileName = basename($parsed['path']);
        } else {
            $this->url = $this->config['url'] . $version['file'];
            $this->fileName = $version['file'];
        }

        return $this->url;
    }

    
    public function download(string $downloadDir = "downloads"): SourceCode
    {
        $url = $this->latestUrl();
        $path= $downloadDir . DIRECTORY_SEPARATOR . $this->fileName;
        $this->downloadUrl(
            url: $url,
            path: $path,
            headers: $headers ?? [],
        );

        return new SourceCode(
            source: $this,
            filePath: $path,
        );
    }

    public function versionLine(): string
    {
        $url = $this->latestUrl();
        return "$url";
    }
}