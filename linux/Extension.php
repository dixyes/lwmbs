<?php

class Extension extends CommonExtension
{
    public function getExtensionArg(): string
    {
        $arg = $this->desc->getArg();
        switch ($this->name) {
            case 'openssl':
                $arg .= ' ' .
                    'OPENSSL_CFLAGS=-I"' . realpath('include') . '" ' .
                    'OPENSSL_LIBS="' . $this->getStaticLibFiles() . '" ';
                break;
            case 'curl':
                $arg .= ' ' .
                    'CURL_CFLAGS=-I"' . realpath('include') . '" ' .
                    'CURL_LIBS="' . $this->getStaticLibFiles() . '" ';
                break;
            case 'bz2':
                $arg = '--with-bz2="' . realpath('.') . '" ';
                break;
            case 'zlib':
                $arg .= ' ' .
                    'ZLIB_CFLAGS=-I"' . realpath('include') . '" ' .
                    'ZLIB_LIBS="' . $this->getStaticLibFiles() . '" ';
                break;
        }
        return $arg;
    }
    public function getStaticLibFiles(): string
    {
        $ret = array_map(fn ($x) => $x->getStaticLibFiles(), $this->getLibraryDependencies());
        return implode(' ', $ret);
    }
    public static function makeExtensionArgs($config): string
    {
        $ret = [];
        $desc = static::getAllExtensionDescs();
        foreach ($desc as $ext) {
            if (array_key_exists($ext->name, $config->exts)) {
                $ret[] = $config->exts[$ext->name]->getExtensionArg();
            } else {
                $ret[] = $ext->getArg() . '=no';
            }
        }
        return implode(' ', $ret);
    }
}
