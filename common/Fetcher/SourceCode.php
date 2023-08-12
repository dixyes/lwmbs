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

class SourceCode
{
    static protected $srcDir = "src";

    public readonly string $path;
    public function __construct(
        public readonly SourceCodeSource $source,
        public readonly ?string $filePath = null,
    ) {
        if (!$filePath && !($source instanceof CloneInterface)) {
            throw new LogicException("SourceCodeSource must implement CloneInterface if filePath is not provided");
        }

        $dir = $source->config['path'] ?: $source->name;
        $this->path = static::$srcDir . DIRECTORY_SEPARATOR . $dir;
    }

    private function prepareSwow(){
        
    }

    public function prepare()
    {
        if ($this->filePath) {
            // extract it
            Log::i("extracting {$this->source->name} source");
            Util::extractSource($this->filePath, $this->path);
        } else {
            // clone it
            Log::i("cloning {$this->source->name} source");
            /** @var CloneInterface $source */
            $source = $this->source;
            $source->clone($this->path);
        }

        switch($this->source->name){
            case 'swow':
                $this->prepareSwow();
                break;
        }
    }

    // public function dumpLicense(?string $path): ?string
    // {
    // }
}
