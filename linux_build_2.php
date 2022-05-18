#!php
<?php

spl_autoload_register(function ($class) {
    if (strpos($class, '\\') !== false) {
        // never here
        throw new Exception('???');
    }

    $osDir = match (PHP_OS_FAMILY) {
        'Windows', 'WINNT', 'Cygwin' => 'windows',
        'Linux' => 'linux',
        'Darwin' => 'macos',
    };

    if (str_starts_with($class, 'Lib') && $class !== 'Library') {
        $libName = substr($class, 3);
        $file = __DIR__ . "/$osDir/libraries/$libName.php";
        require $file;
        return;
    }

    $file = __DIR__ . "/$osDir/$class.php";
    if (is_file($file)) {
        require $file;
    } else {
        require __DIR__ . "/common/$class.php";
    }
});


function mian($argv): int
{
    Util::setErrorHandler();

    $config = new Config($argv);

    $libNames = [
        'zlib',
        'brotli',
        'libiconv',
        'xz',
        'openssl',
        'bzip2',
        'nghttp2',
        'onig',
        'libssh2',
        'libffi',
        'libzip',
        'curl',
    ];

    foreach ($libNames as $name) {
        $lib = new ("Lib$name")($config);
        $lib->prove();
        $config->addLib($lib);
    }

    return 0;
}

exit(mian($argv));
