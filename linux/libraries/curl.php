<?php

class Libcurl implements ILibrary
{
    private string $name = 'curl';
    private array $staticLibs = [
        'libcurl.a',
    ];
    private array $headers = [
        'curl',
    ];
    private array $pkgconfs = [
        'libcurl.pc' => <<<'EOF'
exec_prefix=${prefix}
libdir=${exec_prefix}/lib
includedir=${prefix}/include
supported_protocols="DICT FILE FTP FTPS GOPHER GOPHERS HTTP HTTPS IMAP IMAPS MQTT POP3 POP3S RTSP SCP SFTP SMB SMBS SMTP SMTPS TELNET TFTP"
supported_features="AsynchDNS GSS-API HSTS HTTP2 HTTPS-proxy IDN IPv6 Kerberos Largefile NTLM NTLM_WB PSL SPNEGO SSL TLS-SRP UnixSockets alt-svc brotli libz zstd"

Name: libcurl
URL: https://curl.se/
Description: Library to transfer files with ftp, http, etc.
Version: 7.83.0
Libs: -L${libdir} -lcurl
Libs.private: -lnghttp2 -lidn2 -lssh2 -lssh2 -lpsl -lssl -lcrypto -lssl -lcrypto -lgssapi_krb5 -lzstd -lbrotlidec -lz
Cflags: -I${includedir}
EOF
    ];

    use Library;

    private function build()
    {
        Log::i("building {$this->name}");

        $zlib = '';
        $libzlib = $this->config->getLib('zlib');
        if ($libzlib) {
            $zlib = '-DZLIB_LIBRARY="' . $libzlib->getStaticLibFiles(style:'cmake') . '" ' .
                '-DZLIB_INCLUDE_DIR=' . realpath('include') . ' ';
        }

        $libssh2 = '';
        $liblibssh2 = $this->config->getLib('libssh2');
        if ($liblibssh2) {
            $libssh2 = '-DLIBSSH2_LIBRARY="' . $liblibssh2->getStaticLibFiles(style:'cmake') . '" ' .
                '-DLIBSSH2_INCLUDE_DIR="' . realpath('include') . '" ';
        }

        $brotli = '-DCURL_BROTLI=OFF ';
        $libbrotli = $this->config->getLib('brotli');
        if ($libbrotli) {
            $brotli = '-DCURL_BROTLI=ON ' .
                '-DBROTLIDEC_LIBRARY="' . realpath('lib/libbrotlidec-static.a') . ';' . realpath('lib/libbrotlicommon-static.a') . '" ' .
                '-DBROTLICOMMON_LIBRARY="' . realpath('lib/libbrotlicommon-static.a') . '" ' .
                '-DBROTLI_INCLUDE_DIR="' . realpath('include') . '" ';
        }

        $nghttp2 = '-DUSE_NGHTTP2=OFF ';
        $libnghttp2 = $this->config->getLib('nghttp2');
        if ($libnghttp2) {
            $nghttp2 = '-DUSE_NGHTTP2=ON ' .
                '-DNGHTTP2_LIBRARY="' . $libnghttp2->getStaticLibFiles(style:'cmake') . '" ' .
                '-DNGHTTP2_INCLUDE_DIR="' . realpath('include') . '" ';
        }

        $ret = 0;
        passthru(
            $this->config->setX . ' && ' .
                "cd {$this->sourceDir} && " .
                'rm -rf build && ' .
                'mkdir -p build && ' .
                'cd build && ' .
                "{$this->config->configureEnv} " . $this->config->libc->getCCEnv() . ' cmake ' .
                // '--debug-find ' .
                '-DCMAKE_BUILD_TYPE=Release ' .
                '-DBUILD_SHARED_LIBS=OFF ' .
                $libssh2 .
                $zlib .
                $brotli .
                $nghttp2 .
                '-DCMAKE_INSTALL_PREFIX=/ ' .
                '-DCMAKE_INSTALL_LIBDIR=/lib ' .
                '-DCMAKE_INSTALL_INCLUDEDIR=/include ' .
                '.. && ' .
                "make -j{$this->config->concurrency} && " .
                'make install DESTDIR=' . realpath('.'),
            $ret
        );
        if ($ret !== 0) {
            throw new Exception("failed to build {$this->name}");
        }
    }
}
