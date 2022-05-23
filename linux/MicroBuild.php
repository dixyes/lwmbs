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

    public function build(bool $allStatic = false): void
    {
        Log::i("building micro");
        $ret = 0;

        $extra_libs = implode(' ', $this->config->getAllStaticLibFiles());
        $envs = $this->config->pkgconfEnv . ' ' .
            "CC='{$this->config->cc}' ".
            "CXX='{$this->config->cxx}' ";
        $cflags = $this->config->archCFlags;

        switch ($this->config->libc) {
            case CLib::MUSL_WRAPPER:
            case CLib::GLIBC:
                $cflags .= ' -static-libgcc -I"' . realpath('include') . '"';
                break;
            case CLib::MUSL:
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
            throw new Exception("failed to configure micro");
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
                '--enable-micro' . ($allStatic ? '=all-static' : '') . ' ' .
                ($this->config->zts ? '--enable-zts' : '') . ' ' .
                Extension::makeExtensionArgs($this->config) . ' ' .
                $envs,
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to configure micro");
        }

        $extra_libs .= Util::genExtraLibs($this->config);

        Util::patchConfigHeader($this->config);

        file_put_contents('/tmp/comment', $this->config->noteSection);

        passthru(
            $this->config->setX . ' && ' .
                'cd src/php-src && ' .
                "make -j{$this->config->concurrency} "  .
                'EXTRA_CFLAGS="-g -Os -fno-ident ' . Util::libtoolCCFlags($this->config->tuneCFlags) . '" ' .
                "EXTRA_LIBS=\"$extra_libs\" " .
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
    }
}
