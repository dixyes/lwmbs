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

require_once __DIR__ . '/SourceCodeSource/GithubAssetSourceCodeSource.php';
require_once __DIR__ . '/SourceCodeSource/GithubTarballSourceCodeSource.php';
require_once __DIR__ . '/SourceCodeSource/GithubTagSourceCodeSource.php';
require_once __DIR__ . '/SourceCodeSource/UrlSourceCodeSource.php';
require_once __DIR__ . '/SourceCodeSource/FileListSourceCodeSource.php';
require_once __DIR__ . '/SourceCodeSource/GitSourceCodeSource.php';

abstract class SourceCodeSource extends stdClass
{
    /**
     * github latest release asset file
     */
    const TYPE_GHASSET = 'ghasset';

    /**
     * github latest release tarball
     */
    const TYPE_GHTAR = 'ghtar';

    /**
     * github latest tag tarball
     */
    const TYPE_GHTAG = 'ghtag';

    /**
     * single url, this cannot be auto updated
     */
    const TYPE_URL = 'url';

    /**
     * a file list contains filename href pattern
     */
    const TYPE_FILELIST = 'filelist';

    /**
     * git repository
     */
    const TYPE_GIT = 'git';

    private static array $typeMap = [
        self::TYPE_GHASSET => 'GithubAssetSourceCodeSource',
        self::TYPE_GHTAR => 'GithubTarballSourceCodeSource',
        self::TYPE_GHTAG => 'GithubTagSourceCodeSource',
        self::TYPE_URL => 'UrlSourceCodeSource',
        self::TYPE_FILELIST => 'FileListSourceCodeSource',
        self::TYPE_GIT => 'GitSourceCodeSource',
    ];
    // todo: register?

    final public static function fromConfig(string $name, array|stdClass $config): static
    {
        $config = (array)$config;
        return new static::$typeMap[$config['type']]($name, $config);
    }

    /**
     * @param string $type the type of source code source
     * @param array $config the config of source code source
     */
    public function __construct(
        readonly public string $name,
        readonly public array $config,
    ) {
        $this->validate();
    }

    protected function downloadUrl(string $url, string $path, array $headers = [])
    {
        if (!is_dir(dirname($path))) {
            @mkdir(dirname($path), 0755, true);
        }

        if (is_file($path)) {
            Log::i("file $path already exists, skip downloading");
            return;
        }

        Log::i("downloading $url");
        Util::download(
            url: $url,
            path: $path,
            headers: $headers,
        );
    }

    abstract protected function validate();
    abstract public function download(string $downloadDir = "downloads"): SourceCode;
    abstract public function versionLine(): string;
}
