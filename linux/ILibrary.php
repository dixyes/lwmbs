<?php

interface ILibrary
{
    public function __construct(
        Config $config,
        ?string $sourceDir = null,
    );
    public function getName(): string;
    public function getStaticLibs(): array;
    public function getHeaders(): array;
    public function getStaticLibFiles(string $style = 'autoconf'): string;
    public function prove(): void;
}
