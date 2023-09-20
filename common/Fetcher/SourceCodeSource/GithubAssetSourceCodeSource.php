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

class GithubAssetSourceCodeSource extends SourceCodeSource
{

    protected function validate()
    {
        if (!isset($this->config['repo'])) {
            throw new Exception("key 'repo' is required for GithubAssetSourceCodeSource");
        }
        if (!isset($this->config['match'])) {
            throw new Exception("key 'match' is required for GithubAssetSourceCodeSource");
        }
    }

    public readonly string $url;
    public readonly string $fileName;
    public readonly string $tagName;
    private function latestUrl(): string
    {
        if (isset($this->url)) {
            return $this->url;
        }

        Log::i("finding latest source from github releases assests from {$this->config['repo']} for {$this->name}");

        if (($authHeader = Util::githubHeader())) {
            $headers = [
                $authHeader,
            ];
        }

        $data = json_decode(Util::fetch(
            url: "https://api.github.com/repos/{$this->config['repo']}/releases",
            headers: $headers ?? [],
        ), true);

        $prefer = $data[0];
        for ($i = count($data) - 1; $i >= 0; $i--) {
            if ($data[$i]['prerelease']) {
                continue;
            }
            $prefer = $data[$i];
        }

        $this->tagName = $prefer['tag_name'];
        $url = null;
        foreach ($prefer['assets'] as $asset) {
            if (preg_match('|' . $this->config['match'] . '|', $asset['name'])) {
                $url = $asset['browser_download_url'];
                break;
            }
        }
        if (!$url) {
            throw new Exception("failed to find source");
        }

        $this->fileName = basename($url);

        return ($this->url = $url);
    }

    public function download(string $downloadDir = "downloads"): SourceCode
    {
        if (($authHeader = Util::githubHeader())) {
            $headers = [
                $authHeader,
            ];
        }

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
        return "$url {$this->tagName}";
    }
}
