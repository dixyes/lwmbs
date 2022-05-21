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
false "not implemented"
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

# Pits

1. musl wrapper may not work
2. old version of glibc do not support all-static

# Nothin else
