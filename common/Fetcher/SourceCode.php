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

class SourceCode
{
    static protected $srcDir = "src";

    public readonly string $path;
    public function __construct(
        public readonly SourceCodeSource $source,
        public readonly ?string $filePath = null,
    ) {
        if (!$filePath && !($source instanceof CloneInterface)) {
            throw new LogicException("SourceCodeSource must implement CloneInterface if filePath is not provided");
        }

        $dir = $source->config['path'] ?? $source->name;
        $this->path = static::$srcDir . DIRECTORY_SEPARATOR . $dir;
    }

    private function prepareSwow()
    {
        if (is_link(static::$srcDir . '/php-src/ext/swow')) {
            return;
        }
        Log::i('linking swow');
        $ret = 0;
        if (PHP_OS_FAMILY === 'Windows') {
            passthru(
                'cd ' . static::$srcDir . '/php-src/ext && ' .
                    'mklink /D swow swow-src\ext',
                $ret
            );
        } else {
            passthru(
                'cd ' . static::$srcDir . '/php-src/ext && ' .
                    'ln -s swow-src/ext swow ',
                $ret
            );
        }
        if ($ret != 0) {
            throw new Exception("failed to link swow");
        }
    }

    private function preparePHP()
    {
        Log::i('patching php');

        $version_h = file_get_contents($this->path . '/main/php_version.h');
        preg_match('/#\s*define\s+PHP_MAJOR_VERSION\s+(\d+)\s+#\s*define\s+PHP_MINOR_VERSION\s+(\d+)\s+#\s*define\s+PHP_RELEASE_VERSION\s+(\d+)/m', $version_h, $match);
        // $realVersion = "{$match[1]}.{$match[2]}.{$match[3]}";

        $majMin = "{$match[1]}{$match[2]}";
        $ret = 0;
        passthru(
            'cd ' . $this->path . ' && ' .
                'git checkout HEAD .',
            $ret
        );
        if ($ret != 0) {
            throw new Exception("failed to reset php");
        }

        $patchNames = [
            'static_opcache',
            'static_extensions_win32',
            'cli_checks',
            'disable_huge_page',
            'vcruntime140',
            'win32',
            'zend_stream',
        ];
        $patchNames = array_merge($patchNames, match (PHP_OS_FAMILY) {
            'Windows' => [
                'cli_static',
            ],
            'Darwin' => [
                'macos_iconv',
            ],
            default => [],
        });
        $patches = [];
        $serial = ['80', '81', '82', '83'];
        foreach ($patchNames as $patchName) {
            if (file_exists($this->path . "/sapi/micro/patches/{$patchName}.patch")) {
                $patches[] = "sapi/micro/patches/{$patchName}.patch";
                continue;
            }
            for ($i = array_search($majMin, $serial, true); $i >= 0; $i--) {
                $tryMajMin = $serial[$i];
                if (!file_exists($this->path . "/sapi/micro/patches/{$patchName}_{$tryMajMin}.patch")) {
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
            'cd ' . $this->path . ' && ' .
                (PHP_OS_FAMILY === 'Windows' ? 'type' : 'cat') . ' ' . $patchesStr . ' | patch -p1',
            $ret
        );
        if ($ret != 0) {
            throw new Exception("failed to patch php");
        }

        if ($majMin == '80') {
            // openssl3 patch
            Log::i('patching php for openssl 3');
            $openssl_c = file_get_contents($this->path . '/ext/openssl/openssl.c');
            $openssl_c = preg_replace('/REGISTER_LONG_CONSTANT\s*\(\s*"OPENSSL_SSLV23_PADDING"\s*.+;/', '', $openssl_c);
            file_put_contents($this->path . '/ext/openssl/openssl.c', $openssl_c);
        }
    }

    public function prepare(bool $shallowClone = false)
    {
        if ($this->filePath) {
            // extract it
            Log::i("extracting {$this->source->name} source");
            Util::extractSource($this->filePath, $this->path);
        } else {
            // clone it
            Log::i("cloning {$this->source->name} source");
            /** @var CloneInterface $source */
            $source = $this->source;
            $source->clone($this->path, $shallowClone);
        }

        switch ($this->source->name) {
            case 'swow':
                $this->prepareSwow();
                break;
            case 'php':
                $this->preparePHP();
                break;
        }
    }

    // public function dumpLicense(?string $path): ?string
    // {
    // }
}
