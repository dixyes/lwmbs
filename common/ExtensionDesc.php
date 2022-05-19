<?php

interface ExtensionDesc {
    public function getArg():string;
    public function getLibDeps():array;
    public function getExtDeps():array;
    public function getCustomExtDir(): ?string;
}