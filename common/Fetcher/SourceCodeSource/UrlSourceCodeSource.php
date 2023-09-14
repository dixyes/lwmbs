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

class UrlSourceCodeSource extends SourceCodeSource
{
    protected function validate()
    {
        if (!isset($this->config['url'])) {
            throw new Exception("key 'url' is required for UrlSourceCodeSource");
        }
    }

    public function download(string $downloadDir = "downloads"): SourceCode
    {
        $path = $downloadDir . DIRECTORY_SEPARATOR . basename($this->config['url']);
        $this->downloadUrl(
            url: $this->config['url'],
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
        return $this->config['url'];
    }
}
