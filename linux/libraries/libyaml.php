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

class Liblibyaml extends Library
{
    use LinuxLibraryTrait;
    protected string $name = 'libyaml';
    protected array $staticLibs = [
        'libyaml.a',
    ];
    protected array $headers = [
        'yaml.h',
    ];
    protected array $pkgconfs = [
        'yaml.pc' => <<<'EOF'
exec_prefix=${prefix}
includedir=${prefix}/include
libdir=${exec_prefix}/lib

Name: LibYAML
Description: Library to parse and emit YAML
Version: 0.2.5
Cflags: -I${includedir}
Libs: -L${libdir} -lyaml
EOF,
    ];
    protected array $depNames = [
    ];

    protected function build(): void
    {
        Log::i("building {$this->name}");
        // prepare cmake/config.h.in
        if (!is_file('src/libyaml/cmake/config.h.in')) {            
            mkdir('src/libyaml/cmake');
            file_put_contents('src/libyaml/cmake/config.h.in', <<<'EOF'
#define YAML_VERSION_MAJOR @YAML_VERSION_MAJOR@
#define YAML_VERSION_MINOR @YAML_VERSION_MINOR@
#define YAML_VERSION_PATCH @YAML_VERSION_PATCH@
#define YAML_VERSION_STRING "@YAML_VERSION_STRING@"
EOF);
        }

        // prepare yamlConfig.cmake.in
        if (!is_file('src/libyaml/yamlConfig.cmake.in')) {
            file_put_contents('src/libyaml/yamlConfig.cmake.in', <<<'EOF'
# Config file for the yaml library.
#
# It defines the following variables:
#   yaml_LIBRARIES    - libraries to link against

@PACKAGE_INIT@

set_and_check(yaml_TARGETS "@PACKAGE_CONFIG_DIR_CONFIG@/yamlTargets.cmake")

if(NOT yaml_TARGETS_IMPORTED)
  set(yaml_TARGETS_IMPORTED 1)
  include(${yaml_TARGETS})
endif()

set(yaml_LIBRARIES yaml)

EOF);
        }

        $ret = 0;

        passthru(
            $this->config->setX . ' && ' .
                "cd {$this->sourceDir} && " .
                'rm -rf build && ' .
                'mkdir -p build && ' .
                'cd build && ' .
                "{$this->config->configureEnv} " . ' cmake ' .
                // '--debug-find ' .
                '-DCMAKE_BUILD_TYPE=Release ' .
                '-DBUILD_TESTING=OFF ' .
                '-DBUILD_SHARED_LIBS=OFF ' .
                '-DCMAKE_INSTALL_PREFIX=/ ' .
                '-DCMAKE_INSTALL_LIBDIR=/lib ' .
                '-DCMAKE_INSTALL_INCLUDEDIR=/include ' .
                '-DCMAKE_POLICY_VERSION_MINIMUM=3.5 ' .
                "-DCMAKE_TOOLCHAIN_FILE={$this->config->cmakeToolchainFile} " .
                '.. && ' .
                "make -j{$this->config->concurrency} && " .
                'make install DESTDIR=' . realpath('.'),
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to build {$this->name}");
        }

        $this->makeFakePkgconfs();
    }
}
