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

    public function build(bool $fresh = false, bool $bloat = false): void
    {
        Log::i("building micro");
        $ret = 0;

        if (!$bloat) {
            $extra_libs = implode(' ', $this->config->getAllStaticLibFiles());
        } else {
            $extra_libs = implode(
                ' ',
                array_map(
                    fn ($x) => "-Xcompiler $x",
                    array_filter($this->config->getAllStaticLibFiles())
                )
            );
        }

        $envs = $this->config->pkgconfEnv . ' ' .
            "CC='{$this->config->cc}' " .
            "CXX='{$this->config->cxx}' ";
        $cflags = $this->config->archCFlags;
        $use_lld = '';

        switch ($this->config->libc) {
            case CLib::MUSL_WRAPPER:
            case CLib::GLIBC:
                $cflags .= ' -static-libgcc -I"' . realpath('include') . '"';
                break;
            case CLib::MUSL:
                if (str_ends_with($this->config->cc, 'clang') && Util::findCommand('lld')) {
                    $use_lld = '-Xcompiler -fuse-ld=lld';
                }
                break;
            default:
                throw new Exception('not implemented');
        }

        $envs = "$envs CFLAGS='$cflags' LIBS='-ldl -lpthread'";

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

        passthru(
            $this->config->setX . ' && ' .
                'cd src/php-src && ' .
                './configure ' .
                '--prefix= ' .
                '--with-valgrind=no ' .
                '--enable-shared=no ' .
                '--enable-static=yes ' .
                "--host={$this->config->arch}-unknown-linux " .
                '--disable-all ' .
                '--disable-cgi ' .
                '--disable-phpdbg ' .
                '--enable-micro' . ($this->config->allStatic ? '=all-static' : '') . ' ' .
                ($this->config->zts ? '--enable-zts' : '') . ' ' .
                $this->config->makeExtensionArgs() . ' ' .
                $envs,
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to configure micro");
        }

        $extra_libs .= Util::genExtraLibs($this->config);

        Util::patchConfigHeader($this->config);

        file_put_contents('/tmp/comment', $this->config->noteSection);

        if ($fresh) {
            Log::i('cleanning up');
            passthru(
                $this->config->setX . ' && ' .
                    'cd src/php-src && ' .
                    'make clean',
                $ret
            );
        }

        if ($bloat) {
            Log::i('bloat linking');
            $extra_libs = "-Wl,--whole-archive $extra_libs -Wl,--no-whole-archive";
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

        passthru(
            $this->config->setX . ' && ' .
                'cd src/php-src && ' .
                'sed -i "s|//lib|/lib|g" Makefile && ' .
                "make -j{$this->config->concurrency} " .
                'EXTRA_CFLAGS="-g -Os -fno-ident ' . Util::libtoolCCFlags($this->config->tuneCFlags) . '" ' .
                "EXTRA_LIBS=\"$extra_libs\" " .
                "EXTRA_LDFLAGS_PROGRAM='$cflags $use_lld" .
                ($this->config->allStatic ? ' -all-static' : '') .
                "' " .
                'POST_MICRO_BUILD_COMMANDS="sh -xc \'' .
                'cd sapi/micro && ' .
                "{$this->config->crossCompilePrefix}objcopy --only-keep-debug micro.sfx micro.sfx.debug && " .
                'elfedit --output-osabi linux micro.sfx && ' .
                "{$this->config->crossCompilePrefix}strip --strip-all micro.sfx && " .
                "{$this->config->crossCompilePrefix}objcopy --update-section .comment=/tmp/comment --add-gnu-debuglink=micro.sfx.debug --remove-section=.note micro.sfx'" .
                '" ' .
                'micro',
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to build micro");
        }

        if (php_uname('m') === $this->config->arch) {
            Log::i('running sanity check');
            // remove hello.exe to avoid strange macos behavior on executable changed
            @unlink('hello.exe');
            file_put_contents(
                'hello.exe',
                file_get_contents('src/php-src/sapi/micro/micro.sfx') . '<?php echo "hello";'
            );
            chmod('hello.exe', 0755);
            exec(
                './hello.exe',
                $output,
                $ret
            );
            if ($ret !== 0 || trim(implode('', $output)) !== 'hello') {
                var_dump($ret, $output);
                throw new Exception("micro failed sanity check");
            }
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
