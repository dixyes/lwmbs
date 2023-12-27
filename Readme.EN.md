# lwmbs

A micro/cli build system with strange name ^，few people will use this directly, so whatever

# Usage

```bash
# prepare
# arch (glibc/musl dev only)
pacman -S base-devel cmake \
    vim \
    brotli \
    php xz tar git curl
# alpine (musl)
apk add alpine-sdk automake autoconf bison flex re2c cmake \
    vim \
    zlib-static zlib-dev \
    bzip2 bzip2-static bzip2-dev \
    zstd zstd-static zstd-dev \
    php81 xz tar curl
# centos 7 (oldest glibc amoung common distros)
yum groupinstall 'Development Tools'
yum install epel-release
yum install re2c perl-IPC-Cmd  \
    vim \
    glibc-static \
    xz zstd bzip2 tar curl
# you may also need a working cmake 3.16+ for building curl libzip things
# you need also a working php 8.1 cli (maybe an all-static binary built in alpine?)
# debian varients, suse varients etc...
false "why?"
# bsd
false "will lwmbs support them?"
# macos
brew install bison re2c
export PATH="/opt/homebrew/opt/bison/bin:/opt/homebrew/opt/re2c/bin:$PATH"
# you need also a working php 8.1 cli (brew install it
# windows
# you need VS (better v16 2019+)
# you need perl for openssl (grab a strawberry perl)
# you may want nasm for openssl
# you need a working PHP 8.1 distrubtion (just download it)
# you need php binary sdk from php
```

```bash
# optional
export https_proxy=https://someproxy
export GITHUB_USER=someuser
export GITHUB_TOKEN=ghp_dsad
# not optional
mkdir build
cd build
# prepare sources
../update_source.php ../src.json 8.1
# build micro (unix)
../build_micro.php "" ""
# build micro (win)
../build_micro.php "" "" --phpBinarySDKDir=<path to sdk> --vsVer=<version like 17> --arch=<arch x64/arm64>
```

# Pits

1. musl wrapper may not work
2. old version of glibc do not support all-static, the built binary may have strange bugs
3. statically compiling opcache needs special environment (new gcc+gnuld+binutils/clang+lld)
4. statically compiling cli at Windows needs patch

# CI

There are some flavors of micro binaries built in actions:

- min: minimal build, almost only ffi, you can use ffi to call other things
- lite: min and some common compressions, phar etc.
- max-swow: almost all supported extensions and swow

# Nothin else
