<?php

trait CommonLibraryTrait
{
    use SourceTrait;

    public function __construct(
        private Config $config,
        ?string $sourceDir = null,
        private array $dependencies = [],
    ) {
        $this->sourceDir = $sourceDir ?? ('src' . '/' . $this->name);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDependencies(bool $recursive = false): array
    {
        $ret = $this->dependencies;
        if (!$recursive) {
            return $ret;
        }

        $added = 1;
        while ($added !==0) {
            $added = 0;
            foreach ($ret as $dep) {
                foreach ($dep->getDependencies(true) as $depdep) {
                    if (!in_array($depdep, $ret, true)) {
                        array_push($ret, $depdep);
                        $added++;
                    }
                }
            }
        }

        return $ret;
    }

    public function calcDependency(): void
    {
        foreach ($this->depNames as $depName => $optional) {
            $this->addLibraryDependency($depName, $optional);
        }
    }

    private function addLibraryDependency(string $name, bool $optional = false)
    {
        $depLib =$this->config->getLib($name);
        if (!$depLib) {
            if (!$optional) {
                throw new Exception("{$this->name} requires library $name");
            } else {
                Log::i("enabling {$this->name} without $name");
            }
        } else {
            $this->dependencies[] = $depLib;
        }
    }
}
