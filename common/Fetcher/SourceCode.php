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
            'static_extensions_win32',
            'cli_checks',
            'disable_huge_page',
            'vcruntime140',
            'win32',
            'zend_stream',
        ];
        // opcache needs no static patch since PHP 8.5
        if ((int) $majMin < 85) {
            array_unshift($patchNames, 'static_opcache');
        }
        $patchNames = array_merge($patchNames, match (PHP_OS_FAMILY) {
            'Windows' => [
                'cli_static',
                'comctl32',
            ],
            'Darwin' => [
                'macos_iconv',
            ],
            default => [],
        });
        $patches = [];
        $serial = ['80', '81', '82', '83', '84', '85', '86', '87'];
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

        switch ($majMin) {
            case '80':
                // openssl3 patch
                Log::i('patching php 8.0 for openssl 3');
                $openssl_c = file_get_contents($this->path . '/ext/openssl/openssl.c');
                $openssl_c = preg_replace('/REGISTER_LONG_CONSTANT\s*\(\s*"OPENSSL_SSLV23_PADDING"\s*.+;/', '', $openssl_c);
                file_put_contents($this->path . '/ext/openssl/openssl.c', $openssl_c);
                break;
            case '81':
                // recent php 8.1 releases already support libxml2 2.12+
                $libxml_c = @file_get_contents($this->path . '/ext/libxml/libxml.c');
                if ($libxml_c === false || !str_contains($libxml_c, 'LIBXML_VERSION >= 21200')) {
                    Log::i('patching php 8.1 for libxml2');
                    $patch = 'https://github.com/dixyes/php-src/commit/a9f172466d2a7ddda192d116b4a5a2b1577a45cc.diff';
                    $ret = 0;
                    passthru(
                        'cd ' . $this->path . ' && ' .
                            'curl -L ' . $patch . ' | patch -p1',
                        $ret
                    );
                    if ($ret != 0) {
                        Log::w("failed to patch php 8.1 for libxml new ver");
                    }
                } else {
                    Log::i('php 8.1 already supports libxml2 2.12+');
                }

                // sha1.c still uses K&R definitions, rejected by C23 compilers
                Log::i('patching php 8.1 sha1 for C23');
                $patch = __DIR__ . DIRECTORY_SEPARATOR . 'patches' . DIRECTORY_SEPARATOR . 'php81_sha1_ansi.patch';
                $ret = 0;
                passthru(
                    'cd ' . $this->path . ' && ' .
                        (PHP_OS_FAMILY === 'Windows' ? 'type' : 'cat') . ' "' . $patch . '" | patch -p1',
                    $ret
                );
                if ($ret != 0) {
                    throw new Exception('failed to patch php 8.1 sha1');
                }

                // OpenSSL 4 removed ERR_NUM_ERRORS and made ASN1_STRING opaque
                $openssl_c = @file_get_contents($this->path . '/ext/openssl/openssl.c');
                if ($openssl_c === false || !str_contains($openssl_c, 'PHP_OPENSSL_ERR_BUFFER_SIZE')) {
                    // the backport commit also changes NEWS, which does not match
                    // the 8.1 branch content, so exclude it from the patch
                    Log::i('patching php 8.1 for OpenSSL 4');
                    $patch = 'https://github.com/php/php-src/commit/19255f4b784cedf6dd4bb21e97def348d335dd69.diff';
                    $ret = 0;
                    passthru(
                        'cd ' . $this->path . ' && ' .
                            'curl -L ' . $patch . ' | git apply --exclude=NEWS -p1 --ignore-whitespace',
                        $ret
                    );
                    if ($ret != 0) {
                        throw new Exception('failed to patch php 8.1 for OpenSSL 4');
                    }
                } else {
                    Log::i('php 8.1 already supports OpenSSL 4');
                }

                // libbcmath still uses K&R declarations, rejected by C23 compilers
                $bcmath_add_c = @file_get_contents($this->path . '/ext/bcmath/libbcmath/src/add.c');
                if ($bcmath_add_c === false || str_contains($bcmath_add_c, 'bc_add (n1, n2, result, scale_min)')) {
                    Log::i('patching php 8.1 bcmath for C23');
                    $patch = 'https://github.com/php/php-src/commit/21664fb9357a6a89f3b228a6fc7a0d07b396908b.diff';
                    $ret = 0;
                    passthru(
                        'cd ' . $this->path . ' && ' .
                            'curl -L ' . $patch . ' | git apply --ignore-whitespace -p1',
                        $ret
                    );
                    if ($ret != 0) {
                        throw new Exception('failed to patch php 8.1 bcmath');
                    }
                } else {
                    Log::i('php 8.1 bcmath has ANSI declarations');
                }

                // musl fopencookie patch
                Log::i('patching php 8.1 for musl fopencookie');
                $patch = 'https://github.com/php/php-src/commit/577b8ae4226368e66fee7a9b5c58f9e2428372fc.diff';
                $ret = 0;
                passthru(
                    'cd ' . $this->path . ' && ' .
                        'curl -L ' . $patch . ' | patch -p1',
                    $ret
                );
                if ($ret != 0) {
                    Log::w("failed to patch php 8.1 for musl fopencookie");
                }
                break;
        }

        if ($majMin === '81' || $majMin === '82') {
            // a PHP 8.5 host binary defines a ZEND_VM_KIND userland constant,
            // which collides with the internal constant of those generators
            $vm_gen = @file_get_contents($this->path . '/Zend/zend_vm_gen.php');
            if ($vm_gen !== false && !str_contains($vm_gen, 'ZEND_VM_GEN_KIND')) {
                // the upstream fix needs fuzz to apply to the older generators
                Log::i('patching php zend_vm_gen for PHP 8.5 host');
                $patch = 'https://github.com/php/php-src/commit/d3ef80649e6.diff';
                $ret = 0;
                passthru(
                    'cd ' . $this->path . ' && ' .
                        'curl -L ' . $patch . ' | patch -p1 -F3 --no-backup-if-mismatch',
                    $ret
                );
                if ($ret != 0) {
                    throw new Exception('failed to patch zend_vm_gen for PHP 8.5 host');
                }
            }
        }

        if (PHP_OS_FAMILY === 'Windows' && (int) $majMin >= 85) {
            // MSVC implements the vreinterpretq intrinsics used by zend_simd
            // as macros, a compound literal argument is split at its commas
            $simd = @file_get_contents($this->path . '/Zend/zend_simd.h');
            if ($simd !== false && !str_contains($simd, 'XSSE_SET_S8')) {
                // 8.5 releases use the indented header, master uses another form
                $patch = $majMin === '85'
                    ? 'https://github.com/php/php-src/commit/706299fb393185b3c96a3fec1cfbffbcd21646d8.diff'
                    : 'https://github.com/dixyes/php-src/commit/d2ad27fe98f2d21342db1ccc060c8f2fb298f480.patch';
                Log::i('patching php zend_simd for MSVC arm64');
                $ret = 0;
                passthru(
                    'cd ' . $this->path . ' && ' .
                        'curl -L ' . $patch . ' | git apply --ignore-whitespace -p1',
                    $ret
                );
                if ($ret != 0) {
                    throw new Exception('failed to patch zend_simd for MSVC arm64');
                }
            }
        }
    }

    private function prepareYaml(): void
    {
        // PHP 8.7 reverted zend_is_callable() to its pre-8.5 signature
        $version_h = @file_get_contents(static::$srcDir . '/php-src/main/php_version.h');
        if ($version_h === false) {
            throw new Exception('failed to read PHP version header');
        }
        if (!preg_match('/#\s*define\s+PHP_MAJOR_VERSION\s+(\d+)/', $version_h, $major)
            || !preg_match('/#\s*define\s+PHP_MINOR_VERSION\s+(\d+)/', $version_h, $minor)) {
            throw new Exception('failed to detect PHP version');
        }
        if ((int) "{$major[1]}{$minor[1]}" < 87) {
            return;
        }

        Log::i('patching yaml for PHP 8.7+');
        $patch = __DIR__ . DIRECTORY_SEPARATOR . 'patches' . DIRECTORY_SEPARATOR . 'yaml_php87_compat.patch';
        $ret = 0;
        passthru(
            'cd ' . $this->path . ' && ' .
                (PHP_OS_FAMILY === 'Windows' ? 'type' : 'cat') . ' "' . $patch . '" | patch -p1',
            $ret
        );
        if ($ret != 0) {
            throw new Exception('failed to patch yaml');
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
            case 'yaml':
                $this->prepareYaml();
                break;
        }
    }

    // public function dumpLicense(?string $path): ?string
    // {
    // }
}
