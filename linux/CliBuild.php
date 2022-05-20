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

class CliBuild
{
    public function __construct(
        private Config $config
    ) {
    }

    public function build(bool $allStatic = false): void
    {
        Log::i("building cli");
        $ret = 0;

        $extra_libs = implode(' ', $this->config->getAllStaticLibFiles());
        $envs = $this->config->configureEnv;
        $seds = null;

        switch ($this->config->libc) {
            case CLib::MUSL_WRAPPER:
                $envs .= ' CFLAGS="-static-libgcc -I' . realpath('include') . '" ' .
                    $this->config->libc->getCCEnv(true);
                $seds =
                    ' sed -i "s|#define HAVE_STRLCPY 1||g" main/php_config.h && ' .
                    ' sed -i "s|#define HAVE_STRLCAT 1||g" main/php_config.h';
                break;
            case CLib::GLIBC:
                $envs = ' CFLAGS="-static-libgcc -I' . realpath('include') . '" ';
                break;
            default:
                throw new Exception('not implemented');
        }

        Util::patchConfigure($this->config);

        passthru(
            $this->config->setX . ' && ' .
                'cd src/php-src && ' .
                './configure ' .
                '--prefix= ' .
                '--with-valgrind=no ' .
                '--enable-shared=no ' .
                '--enable-static=yes ' .
                '--disable-all ' .
                '--disable-cgi ' .
                '--disable-phpdbg ' .
                '--enable-cli ' .
                ($this->config->zts ? '--enable-zts' : '') . ' ' .
                Extension::makeExtensionArgs($this->config) . ' ' .
                $envs . ' ' .
                ' && ' .
                ($seds ?? ':'),
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to configure cli");
        }
    
        $extra_libs .= Util::genExtraLibs($this->config);
        file_put_contents('/tmp/comment', "... If we meet some day, and you think this stuff is worth it, you can buy me a beer in return.\0");

        passthru(
            $this->config->setX . ' && ' .
                'cd src/php-src && ' .
                "make -j{$this->config->concurrency} "  .
                'EXTRA_CFLAGS="-g -Os -fno-ident -Xcompiler -march=nehalem -Xcompiler -mtune=haswell" ' .
                "EXTRA_LIBS=\"$extra_libs\" " .
                ($allStatic ? 'EXTRA_LDFLAGS_PROGRAM=-all-static ' : '') .
                'cli && ' .
                'cd sapi/cli && ' .
                'objcopy --only-keep-debug php php.debug && ' .
                'elfedit --output-osabi linux php && ' .
                'strip --strip-all php && ' .
                'objcopy --update-section .comment=/tmp/comment --add-gnu-debuglink=php.debug --remove-section=.note php',
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to build cli");
        }
    }
}
