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

    public function build(bool $fresh = false, bool $bloat = false): void
    {
        Log::i("building cli");
        $ret = 0;

        $extra_libs = $this->config->getFrameworks(true) . ' ';
        if (!$bloat) {
            $extra_libs .= implode(' ', $this->config->getAllStaticLibFiles());
        } else {
            Log::i('bloat linking');
            $extra_libs .= implode(
                ' ',
                array_map(
                    fn ($x) => "-Wl,-force_load,$x",
                    array_filter($this->config->getAllStaticLibFiles())
                )
            );
        }

        passthru(
            $this->config->setX . ' && ' .
                'cd src/php-src && ' .
                './buildconf --force',
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to buildconf for cli");
        }
    
        Util::patchPHPConfigure($this->config);

        passthru(
            $this->config->setX . ' && ' .
                'cd src/php-src && ' .
                './configure ' .
                '--prefix= ' .
                '--with-valgrind=no ' .
                '--enable-shared=no ' .
                '--enable-static=yes ' .
                "--host={$this->config->gnuArch}-apple-darwin " .
                "CFLAGS='{$this->config->archCFlags} -Werror=unknown-warning-option' " .
                '--disable-all ' .
                '--disable-cgi ' .
                '--disable-phpdbg ' .
                '--enable-cli ' .
                ($this->config->zts ? '--enable-zts' : '') . ' ' .
                $this->config->makeExtensionArgs() . ' ' .
                $this->config->configureEnv,
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to configure cli");
        }

        if ($fresh) {
            Log::i('cleanning up');
            passthru(
                $this->config->setX . ' && ' .
                    'cd src/php-src && ' .
                    'make clean'
                ,
                $ret
            );
        }

        passthru(
            $this->config->setX . ' && ' .
                'cd src/php-src && ' .
                "make -j{$this->config->concurrency} "  .
                'EXTRA_CFLAGS="-g -Os -fno-ident" ' .
                "EXTRA_LIBS=\"$extra_libs -lresolv\" " .
                // TODO: comment things
                'cli &&' .
                'dsymutil -f sapi/cli/php'
            ,
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to build cli");
        }

        if (php_uname('m') === $this->config->arch) {
            Log::i('running sanity check');
            exec(
                $this->config->setX . ' && ' .
                    'src/php-src/sapi/cli/php -r "echo \"hello\";"',
                $output,
                $ret
            );
            if ($ret !== 0 || trim(implode('', $output)) !== 'hello') {
                throw new Exception("cli failed sanity check");
            }
        }
    }
}
