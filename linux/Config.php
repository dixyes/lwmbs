<?php

class Config extends CommonConfig
{
    // TODO: workspace
    //public string $workspace = '.';
    public string $setX = 'set -x';
    public string $configureEnv = '';
    public CLib $libc;
    public string $arch;

    public function __construct()
    {
        $lackingCommands = Util::lackingCommands(Util::NEEDED_COMMANDS);
        if ($lackingCommands) {
            throw new Exception("missing commands: " . implode(', ', $lackingCommands));
        }
        $this->configureEnv = 'PKG_CONFIG_PATH=' . realpath('lib/pkgconfig');
        $this->libc = Util::chooseLibc();
        $this->concurrency = Util::getCpuCount();
        $this->arch = php_uname('m');
    }

    public function getLib(string $name): ?ILibrary
    {
        return $this->libs[$name] ?? null;
    }

    public function makeAutoconfArgs(string $name, array $libSpecs): string
    {
        $ret = '';
        foreach ($libSpecs as $libName => $arr) {
            /**
             * @var null|Library $lib
             */
            $lib = $this->getLib($libName);

            $arr = $arr ?? [];

            $disableArgs = $arr[0]??null;
            $prefix = $arr[1]??null;
            if ($lib) {
                Log::i("{$name} \033[32;1mwith\033[0;1m {$libName} support");
                $ret .= $lib->makeAutoconfEnv($prefix) . ' ';
            } else {
                Log::i("{$name} \033[31;1mwithout\033[0;1m {$libName} support");
                $ret .= ($disableArgs ?? "--with-$libName=no") . ' ';
            }
        }
        return rtrim($ret);
    }
}
