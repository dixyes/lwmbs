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

trait UnixExtensionTrait
{
    public function getExtensionEnabledArg(): string
    {
        $arg = $this->desc->getArg();
        switch ($this->name) {
            case 'redis':
                $arg = '--enable-redis';
                if ($this->config->getLib('zstd')) {
                    $arg .= ' --enable-redis-zstd --with-libzstd="' . realpath('.') . '" ';
                }
                break;
            case 'yaml':
                $arg .= ' --with-yaml="' . realpath('.') . '" ';
                break;
            case 'zstd':
                $arg .= ' --with-libzstd';
                break;
            case 'bz2':
                $arg = '--with-bz2="' . realpath('.') . '" ';
                break;
            case 'openssl':
                $arg .= ' ' .
                    'OPENSSL_CFLAGS=-I"' . realpath('include') . '" ' .
                    'OPENSSL_LIBS="' . $this->getStaticLibFiles() . '" ';
                break;
            case 'curl':
                $arg .= ' ' .
                    'CURL_CFLAGS=-I"' . realpath('include') . '" ' .
                    'CURL_LIBS="' . $this->getStaticLibFiles() . '" ';
                break;
            case 'gd':
                $arg .= ' ' .
                    'PNG_CFLAGS=-I"' . realpath('include') . '" ' .
                    'PNG_LIBS="' . $this->getStaticLibFiles() . '" ';
                // TODO: other libraries
            case 'phar':
            case 'zlib':
                $arg .= ' ' .
                    'ZLIB_CFLAGS=-I"' . realpath('include') . '" ' .
                    'ZLIB_LIBS="' . $this->getStaticLibFiles() . '" ';
                break;
            case 'xml': // xml may use expat
                if ($this->getLibraryDependencies()['expat'] ?? null) {
                    $arg .= ' --with-expat="' . realpath('.') . '" ' .
                        'EXPAT_CFLAGS=-I"' . realpath('include') . '" ' .
                        'EXPAT_LIBS="' . $this->getStaticLibFiles() . '" ';
                    break;
                }
            case 'soap':
            case 'xmlreader':
            case 'xmlwriter':
            case 'dom':
                $arg .= ' --with-libxml="' . realpath('.') . '" ' .
                    'LIBXML_CFLAGS=-I"' . realpath('include/libxml2') . '" ' .
                    'LIBXML_LIBS="' . $this->getStaticLibFiles() . '" ';
                break;
            case 'ffi':
                $arg .= ' ' .
                    'FFI_CFLAGS=-I"' . realpath('include') . '" ' .
                    'FFI_LIBS="' . $this->getStaticLibFiles() . '" ';
                break;
            case 'zip':
                $arg .= ' ' .
                    'LIBZIP_CFLAGS=-I"' . realpath('include') . '" ' .
                    'LIBZIP_LIBS="' . $this->getStaticLibFiles() . '" ';
                break;
            case 'mbregex':
                $arg .= ' ' .
                    'ONIG_CFLAGS=-I"' . realpath('include') . '" ' .
                    'ONIG_LIBS="' . $this->getStaticLibFiles() . '" ';
                break;
            case 'swow':
                if ($this->config->getLib('openssl')) {
                    $arg .= ' --enable-swow-ssl';
                }
                if ($this->config->getLib('curl')) {
                    $arg .= ' --enable-swow-curl';
                }
                break;
        }
        return $arg;
    }

    public function getStaticLibFiles(): string
    {
        $ret = array_map(
            fn ($x) => $x->getStaticLibFiles(),
            $this->getLibraryDependencies(recursive: true)
        );
        return implode(' ', $ret);
    }
}
