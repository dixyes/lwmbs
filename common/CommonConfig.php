<?php

class CommonConfig extends \stdClass {
    public int $concurrency = 1;
    protected array $libs = [];
    public function addLib(ILibrary $lib):void {
        $this->libs[$lib->getName()] = $lib;
    }

    public function getLib(string $name) {
        return $this->libs[$name] ?? null;
    }
}
