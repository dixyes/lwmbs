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

require_once __DIR__ . '/../CloneInterface.php';

class GitSourceCodeSource extends SourceCodeSource implements CloneInterface
{
    protected function validate()
    {
        if (!isset($this->config['url'])) {
            throw new Exception("key 'url' is required for GitSourceCodeSource");
        }
        if (!isset($this->config['ref'])) {
            throw new Exception("key 'ref' is required for GitSourceCodeSource");
        }
    }

    public readonly string $revision;
    private function latestRevision(): string
    {
        $output = shell_exec("git ls-remote {$this->config['url']} {$this->config['ref']}");
        $revision = explode("\t", $output)[0];

        return ($this->revision = $revision);
    }

    public function download(string $downloadDir = 'downloads'): SourceCode
    {
        return new SourceCode(
            source: $this,
            filePath: null,
        );
    }

    public function versionLine(): string
    {
        $rev = $this->latestRevision();
        return "{$this->config['url']} $rev";
    }

    public function clone(string $dest, bool $shallowClone = false): void
    {
        if (!is_dir($dest)) {
            // clone if not exist
            Log::i("git clone {$this->config['url']} {$this->config['ref']}");

            $args = "--branch {$this->config['ref']}";
            $args .= $shallowClone ? ' --depth 1 --single-branch' : '';
            $args .= ' --recurse-submodules';
            $args .= ' --config core.autocrlf=false';

            passthru("git clone {$args} {$this->config['url']} $dest", $ret);
            if ($ret !== 0) {
                throw new Exception("git clone failed");
            }
        } else {
            // fetch if exist
            Log::i("git fetch -C $dest");
            passthru("cd $dest && git fetch origin {$this->config['ref']}", $ret);
            if ($ret !== 0) {
                throw new Exception("git fetch failed");
            }

            Log::i("git -C $dest checkout HEAD .");
            passthru("cd $dest && git checkout HEAD .", $ret);
            if ($ret !== 0) {
                throw new Exception("git checkout failed");
            }

            Log::i("git -C $dest checkout FETCH_HEAD");
            passthru("cd $dest && git checkout FETCH_HEAD", $ret);
            if ($ret !== 0) {
                throw new Exception("git checkout failed");
            }
        }


        Log::i("git -C $dest submodule update --init --recursive");
        passthru("cd $dest && git submodule update --init --recursive", $ret);
        if ($ret !== 0) {
            throw new Exception("git submodule update failed");
        }
    }
}
