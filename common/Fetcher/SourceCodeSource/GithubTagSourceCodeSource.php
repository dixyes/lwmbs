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

class GithubTagSourceCodeSource extends SourceCodeSource
{

    protected function validate()
    {
        if (!isset($this->config['repo'])) {
            throw new Exception("key 'repo' is required for GithubTagSourceCodeSource");
        }
        if (!isset($this->config['match'])) {
            throw new Exception("key 'match' is required for GithubTagSourceCodeSource");
        }
    }

    private static function normalizeTagVersion(string $tagVersion): string
    {
        if (preg_match('/^([0-9]+)\.([0-9]+)\.([0-9]+)(?:-([0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*))?(?:\+[0-9A-Za-z-]+)?$/', $tagVersion)) {
            return $tagVersion;
        }
        $tagVersion = str_replace('_', '.', $tagVersion);
        $tagVersion = str_replace('-', '.', $tagVersion);
        return $tagVersion;
    }

    public readonly string $url;
    public readonly string $fileName;
    public readonly string $tagName;
    private function latestUrl(): string
    {
        if (isset($this->url)) {
            return $this->url;
        }

        Log::i("finding latest source from github releases tags from {$this->config['repo']} for {$this->name}");

        if (($authHeader = Util::githubHeader())) {
            $headers = [
                $authHeader,
            ];
        }

        $data = json_decode(Util::fetch(
            url: "https://api.github.com/repos/{$this->config['repo']}/tags",
            headers: $headers ?? [],
        ), true);

        $tags = array_map(function ($tagData) {
            preg_match($this->config['match'], $tagData['name'], $match);
            if (!$match) {
                return null;
            }
            return [
                'name' => $tagData['name'],
                'version' => $match['version'],
                'tarball_url' => $tagData['tarball_url'],
            ];
        }, $data);
        $tags = array_filter($tags);
        usort(
            $tags,
            function ($a, $b) {
                return (version_compare(
                    static::normalizeTagVersion($a['version']),
                    static::normalizeTagVersion($b['version']),
                    '<'
                )) ? 1 : -1;
            }
        );

        if (!$tags) {
            throw new Exception("failed to find source");
        }

        $this->url = $tags[0]['tarball_url'];
        $this->tagName = $tags[0]['name'];
        Log::i("chosen {$this->url} for {$this->name} {$this->tagName}");

        $headers = Util::fetch(
            url: $this->url,
            method: 'HEAD',
            headers: $headers ?? [],
        );
        preg_match('/^content-disposition:\s+attachment;\s*filename=("{0,1})(?<filename>.+\.tar\.gz)\1/im', $headers, $matches);
        if ($matches) {
            $this->fileName = $matches['filename'];
        } else {
            $this->fileName = "{$this->name}-{$this->tagName}.tar.gz";
        }

        return $this->url;
    }

    public function download(string $downloadDir = "downloads"): SourceCode
    {
        if (($authHeader = Util::githubHeader())) {
            $headers = [
                $authHeader,
            ];
        }

        $url = $this->latestUrl();
        $path = $downloadDir . DIRECTORY_SEPARATOR . $this->fileName;
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
