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

class Extension extends CommonExtension
{
    public function getExtensionArg(): string
    {
        $arg = $this->desc->getArg();
        switch ($this->name) {
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
            case 'bz2':
                $arg = '--with-bz2="' . realpath('.') . '" ';
                break;
            case 'zlib':
                $arg .= ' ' .
                    'ZLIB_CFLAGS=-I"' . realpath('include') . '" ' .
                    'ZLIB_LIBS="' . $this->getStaticLibFiles() . '" ';
                break;
        }
        return $arg;
    }
    public function getStaticLibFiles(): string
    {
        $ret = array_map(fn ($x) => $x->getStaticLibFiles(), $this->getLibraryDependencies());
        return implode(' ', $ret);
    }
    public static function makeExtensionArgs($config): string
    {
        $ret = [];
        $desc = static::getAllExtensionDescs();
        foreach ($desc as $ext) {
            if (array_key_exists($ext->name, $config->exts)) {
                $ret[] = $config->exts[$ext->name]->getExtensionArg();
            } else {
                $ret[] = $ext->getArg() . '=no';
            }
        }
        return implode(' ', $ret);
    }
}
