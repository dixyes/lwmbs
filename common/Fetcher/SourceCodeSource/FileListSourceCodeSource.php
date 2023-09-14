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
            $versions[$version] = $matches['file'][$i];
        }
        uksort($versions, 'version_compare');

        $this->url = $this->config['url'] . end($versions);
        $this->fileName = end($versions);

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