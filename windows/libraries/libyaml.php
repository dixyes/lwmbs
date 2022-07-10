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
    use WindowsLibraryTrait;
    protected string $name = 'libyaml';
    protected array $staticLibs = [
        'yaml.lib',
    ];
    protected array $headers = [
        'yaml.h',
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
        if (is_dir("{$this->sourceDir}\\builddir")) {
            exec("rmdir /s /q \"{$this->sourceDir}\\builddir\"", result_code: $ret);
            if ($ret !== 0) {
                throw new Exception("failed to clean up {$this->name}");
            }
        }
        passthru(
            "cd {$this->sourceDir} && " .
                'cmake -B builddir ' .
                    "-A \"{$this->config->cmakeArch}\" " .
                    "-G \"{$this->config->cmakeGeneratorName}\" " .
                    '-DCMAKE_BUILD_TYPE=RelWithDebInfo ' .
                    '-DBUILD_TESTING=OFF ' .
                    '-DBUILD_SHARED_LIBS=OFF ' .
                    //'-DCMAKE_C_FLAGS_MINSIZEREL="/MT /O1 /Ob1 /DNDEBUG" ' .
                    '-DCMAKE_INSTALL_PREFIX="' . realpath('deps') . '" ' .
                    "-DCMAKE_TOOLCHAIN_FILE={$this->config->cmakeToolchainFile} " .
                '&& ' .
                //--config MinSizeRel 
                "cmake --build builddir --config RelWithDebInfo --target install -j {$this->config->concurrency}",
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to build {$this->name}");
        }

        // patch yaml.h
        $yaml_h = file_get_contents('deps\include\yaml.h');
        $yaml_h = preg_replace('/defined\s*\(\s*YAML_DECLARE_STATIC\s*\)/', '1', $yaml_h);
        file_put_contents('deps\include\yaml.h', $yaml_h);
    }
}
