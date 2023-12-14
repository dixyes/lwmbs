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

class MicroBuild
{
    public function __construct(
        private Config $config
    ) {
    }

    public function build(bool $fresh = false, bool $bloat = false, bool $fakeCli = false): void
    {
        Log::i("building micro");
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
            throw new Exception("failed to buildconf for micro");
        }
    
        Util::patchPHPConfigure($this->config);

        if (
            $this->config->getLib('libxml2') ||
            $this->config->getExt('iconv')
        ) {
            $extra_libs .= ' -liconv';
        }
        if ($this->config->getLib('webview')) {
            $extra_libs = preg_replace('/[^ ]+webview_static\.a/', '-Wl,-force_load,\0', $extra_libs);
        }

        passthru(
            $this->config->setX . ' && ' .
                'cd src/php-src && ' .
                './configure ' .
                '--prefix= ' .
                '--with-valgrind=no ' .
                '--enable-shared=no ' .
                '--enable-static=yes ' .
                "--host={$this->config->gnuArch}-apple-darwin " .
                "CFLAGS='{$this->config->cFlags} -Werror=unknown-warning-option' " .
                '--disable-all ' .
                '--disable-cgi ' .
                '--disable-phpdbg ' .
                '--enable-micro ' .
                ($this->config->zts ? '--enable-zts' : '') . ' ' .
                $this->config->makeExtensionArgs() . ' ' .
                $this->config->configureEnv,
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to configure micro");
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

        if ($this->config->getExt('phar')) {
            $pharPatched = true;
            passthru(
                "cd src/php-src && patch -p1 < sapi/micro/patches/phar.patch",
                $ret
            );
            if ($ret !== 0) {
                Log::e("failed to patch phar");
                $pharPatched = false;
            }
        }

        $lcpp = '';
        if ($this->config->useCPP()) {
            $lcpp = '-lc++';
        }

        passthru(
            $this->config->setX . ' && ' .
                'cd src/php-src && ' .
                "make -j{$this->config->concurrency} " .
                'EXTRA_CFLAGS="-g -Os -fno-ident' .
                    ($fakeCli ? ' -DPHP_MICRO_FAKE_CLI' : '') .
                '" ' .
                "EXTRA_LIBS=\"$extra_libs -lresolv $lcpp\" " .
                "STRIP=\"dsymutil -f \" " .
                // TODO: comment things
                'micro',
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to build micro");
        }

        if (php_uname('m') === $this->config->arch) {
            Util::sanityCheck();
            Util::sapiNameCheck(!$fakeCli ? 'micro' : 'cli');
        }

        if ($this->config->getExt('phar') && $pharPatched) {
            passthru(
                "cd src/php-src && patch -p1 -R < sapi/micro/patches/phar.patch",
                $ret
            );
            if ($ret !== 0) {
                throw new Exception("failed to recover phar patch");
            }
        }
    }
}
