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
false "not implemented"
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
# build micro
../build_micro.php
```

# 坑

1. musl wrapper可能用不了
2. glibc旧版本不兹磁全静态编译
3. 

# 没了
