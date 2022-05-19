<?php

trait LinuxLibraryTrait
{
    use CommonLibraryTrait;

    public function prove(bool $forceBuild = false): void
    {
        //Log::i("checking cpp for {$this->name}");
        //passthru("find {$this->sourceDir} -type f -name '*.cpp'");
        //return;
        foreach ($this->staticLibs as $name) {
            if (!file_exists("lib/{$name}")) {
                goto make;
            }
        }
        foreach ($this->headers as $name) {
            if (!file_exists("include/{$name}")) {
                goto make;
            }
        }
        Log::i("{$this->name} already proven");
        return;
        make:

        $staticLibPathes = Util::findStaticLibs($this->staticLibs);
        $headerPathes = Util::findHeaders($this->headers);
        if (!$staticLibPathes || !$headerPathes || $forceBuild) {
            $this->build();
        } else {
            if ($this->config->libc === CLib::MUSL_WRAPPER) {
                Log::w("libc type may not match, this may cause strange symbol missing");
            }
            $this->copyExist($staticLibPathes, $headerPathes);
        }
        foreach ($this->pkgconfs as $name => $_) {
            Util::fixPkgConfig("lib/pkgconfig/$name");
        }

        Log::i("{$this->name} proven");
    }

    protected function copyExist(array $staticLibPathes, array $headerPathes): void
    {
        if (!$staticLibPathes || !$headerPathes) {
            throw new Exception('??? staticLibPathes or headerPathes is null');
        }
        Log::i("using system {$this->name}");
        foreach ($staticLibPathes as [$path, $staticLib]) {
            @mkdir('lib/' . dirname($staticLib), recursive: true);
            Log::i("copy $path/$staticLib to lib/$staticLib");
            copy("$path/$staticLib", "lib/" . $staticLib);
        }
        foreach ($headerPathes as [$path, $header]) {
            @mkdir('include/' . dirname($header), recursive: true);
            Log::i("copy $path/$header to include/$header");
            if (is_dir("$path/$header")) {
                Util::copyDir("$path/$header", "include/$header");
            } else {
                copy("$path/$header", "include/$header");
            }
        }
        $this->makeFakePkgconfs();
    }
    public function getName(): string
    {
        return $this->name;
    }
    public function getStaticLibs(): array
    {
        return $this->staticLibs;
    }
    public function getHeaders(): array
    {
        return $this->headers;
    }
    public function getStaticLibFiles(string $style = 'autoconf', bool $recursive = true): string
    {
        $libs = [$this];
        if ($recursive) {
            array_unshift($libs, ...$this->getDependencies(recursive: true));
        }

        $sep = match ($style) {
            'autoconf' => ' ',
            'cmake'  => ';',
        };
        $ret = [];
        foreach ($libs as $lib) {
            $libFiles = [];
            foreach ($lib->getStaticLibs() as $name) {
                $name = str_replace(' ', '\ ', realpath("lib/$name"));
                $name = str_replace('"', '\"', $name);
                $libFiles[] = $name;
            }
            array_unshift($ret, implode($sep, $libFiles));
        }
        return implode($sep, $ret);
    }
    public function makeAutoconfEnv(string $prefix = null): string
    {
        if ($prefix === null) {
            $prefix = str_replace('-', '_', strtoupper($this->name));
        }
        return $prefix . '_CFLAGS="-I' . realpath('include') . '" ' .
            $prefix . '_LIBS="' . $this->getStaticLibFiles() . '"';
    }
    protected function makeFakePkgconfs()
    {
        foreach ($this->pkgconfs as $name => $content) {
            file_put_contents("lib/pkgconfig/$name", 'prefix=' . realpath('') . "\n" . $content);
        }
    }
}
