<?php

enum CLib
{
    case GLIBC;
    case MUSL;
    case MUSL_WRAPPER;

    public function getLDInterpreter(): string
    {
        $arch = php_uname('m');

        switch ($arch) {
            case 'x86_64':
                return match ($this) {
                    static::GLIBC => 'ld-linux-x86-64.so.2',
                    static::MUSL => 'ld-musl-x86-64.so.1',
                    static::MUSL_WRAPPER => 'ld-musl-x86-64.so.1',
                };
            default:
                throw new Exception("Unsupported architecture: " . $arch);
        }
    }

    public function getCC(): string
    {
        return match ($this) {
            static::GLIBC => 'cc',
            static::MUSL => 'cc',
            static::MUSL_WRAPPER => 'musl-gcc',
        };
    }

    public function getCXX(): string
    {
        return $this->getCC() . ' -x c++';
    }

    public function getCPP(): string
    {
        return $this->getCC() . ' -E';
    }

    public function getCCEnv(bool $usedCXX = false): string
    {
        return match ($this) {
            static::GLIBC => '',
            static::MUSL => '',
            static::MUSL_WRAPPER => 'CC=musl-gcc' . ($usedCXX ? ' CXX="musl-gcc -x c++"' : ''),
        };
    }
}
