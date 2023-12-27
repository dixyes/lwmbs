# lwmbs

一个 ^ 名字很奇怪的micro/cli构建系统，理论上不会有很多人看到这个项目所以无所谓了

# 用法

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

# 坑

1. musl wrapper可能用不了
2. glibc旧版本不兹磁全静态编译，全静态编译出来的东西也肯能有玄学bug
3. opcache静态编译需要一些奇怪的环境（较新的gcc+gnuld+binutils/clang+lld）
4. Windows下静态编译cli需要patch

# CI

actions中构建了若干风味的二进制

- min：最小化构建，只有ffi之类的东西，你可以用ffi去调用别的东西
- lite：比min多了一些常见压缩，phar之类的
- max-swow：基本上所有支持的扩展和swow

# 没了
