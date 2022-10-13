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
    public function getExtensionEnabledArg(): string
    {
        $arg = $this->desc->getArg();
        switch ($this->name) {
            case 'redis':
                // $arg = '--enable-redis';
                // if ($this->config->getLib('zstd')) {
                //     $arg .= ' --enable-redis-zstd --with-libzstd ';
                // }
                break;
            case 'xml':
            case 'soap':
            case 'xmlreader':
            case 'xmlwriter':
            case 'dom':
                $arg .= ' --with-libxml ';
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
