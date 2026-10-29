<?php
/**
 * Copyright (c) 2022 Yun Dou <dixyes@gmail.com>
 *
 * lwmbs is licensed under Mulan PSL v2. You can use this
 * software according to the terms and conditions of the
 * Mulan PSL v2. You may obtain a copy of Mulan PSL v2 at:
 *
 * http://license.coscl.org.cn/MulanPSL2
 *
 * THIS SOFTWARE IS PROVIDED ON AN "AS IS" BASIS,
 * WITHOUT WARRANTIES OF ANY KIND, EITHER EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO NON-INFRINGEMENT,
 * MERCHANTABILITY OR FIT FOR A PARTICULAR PURPOSE.
 *
 * See the Mulan PSL v2 for more details.
 */

declare(strict_types=1);

final class Util
{
    use CommonUtilTrait;
    public static function getCpuCount(): int
    {
        return (int)shell_exec('echo %NUMBER_OF_PROCESSORS%');
    }

    public static function makeCmakeToolchainFile(
        string $targetArch,
        string $cflags = '/MT /O1 /Ob1 /DNDEBUG /D_ACRTIMP= /D_CRTIMP=',
        string $ldflags = '/nodefaultlib:msvcrt /nodefaultlib:msvcrtd /defaultlib:libcmt',
    ):string {
        Log::i("making cmake tool chain file for $targetArch with CFLAGS='$cflags'");
        $root = str_replace('\\', '\\\\', realpath('deps'));
        $toolchain = <<<CMAKE
set(CMAKE_SYSTEM_NAME Windows)
SET(CMAKE_SYSTEM_PROCESSOR $targetArch)
SET(CMAKE_C_FLAGS "$cflags")
SET(CMAKE_C_FLAGS_DEBUG "$cflags")
SET(CMAKE_CXX_FLAGS "$cflags")
SET(CMAKE_CXX_FLAGS_DEBUG "$cflags")
SET(CMAKE_EXE_LINKER_FLAGS "$ldflags")
SET(CMAKE_FIND_ROOT_PATH "$root")
SET(CMAKE_MSVC_RUNTIME_LIBRARY MultiThreaded)
# CMake 4.0+ adds MSVC runtime checks to Debug targets through
# CMAKE_MSVC_RUNTIME_CHECKS, /RTC1 conflicts with the /O1 in CMAKE_C_FLAGS,
# and try_compile builds its test programs in the Debug configuration
SET(CMAKE_MSVC_RUNTIME_CHECKS "")
CMAKE;
        file_put_contents('./toolchain.cmake', $toolchain);
        return realpath('./toolchain.cmake');
    }

    public static function patchLibxml() {
        self::backportLibxml2();
        $config_w32 = file_get_contents('src\php-src\ext\libxml\config.w32');
        // php 8.5 moved the libiconv check into a compound expression,
        // older versions have it as a standalone condition line
        $config_w32 = preg_replace('/^\s*(?:CHECK_LIB\("libiconv|\(\(PHP_ICONV)[^\n]*&&[^\n]*\n/m', '', $config_w32);
        $config_w32 = preg_replace('/ADD_EXTENSION_DEP\s*\(\s*\'libxml\'\s*,\s*\'iconv\'\s*\)/', 'true', $config_w32);
        if (stripos($config_w32, 'libiconv') !== false || stripos($config_w32, 'PHP_ICONV') !== false) {
            throw new Exception('failed to remove the iconv requirement from ext/libxml/config.w32');
        }
        file_put_contents('src\php-src\ext\libxml\config.w32', $config_w32);
        $config_w32 = file_get_contents('src\php-src\win32\build\config.w32');
        $config_w32 = preg_replace('/dllmain\.c\s+/', '', $config_w32);
        file_put_contents('src\php-src\win32\build\config.w32', $config_w32);
    }

    // php-src bc7537db splits the libxml2 export list for 2.15 on 8.4 and later,
    // apply the same split to 8.1 - 8.3
    private static function backportLibxml2(): void
    {
        $version_h = @file_get_contents('src\php-src\main\php_version.h');
        if ($version_h === false) {
            throw new Exception('failed to read PHP version header');
        }
        if (!preg_match('/#\s*define\s+PHP_MAJOR_VERSION\s+(\d+)/', $version_h, $major)
            || !preg_match('/#\s*define\s+PHP_MINOR_VERSION\s+(\d+)/', $version_h, $minor)) {
            throw new Exception('failed to detect PHP version');
        }
        $phpVer = "{$major[1]}.{$minor[1]}";
        $patchName = match ($phpVer) {
            '8.1' => 'libxml2_215_81.patch',
            '8.2' => 'libxml2_215_82.patch',
            '8.3' => 'libxml2_215_83.patch',
            default => null,
        };
        if ($patchName === null) {
            return;
        }

        $config_w32 = file_get_contents('src\php-src\ext\libxml\config.w32');
        if (str_contains($config_w32, 'php_libxml2_legacy.def')) {
            Log::i('libxml2 2.15 backport already applied');
            return;
        }

        Log::i("applying libxml2 2.15 backport for PHP {$phpVer}");
        // the patch adds this file, remove a stale copy first
        $legacy = 'src\php-src\ext\libxml\php_libxml2_legacy.def';
        if (is_file($legacy)) {
            unlink($legacy);
        }
        $patch = __DIR__ . DIRECTORY_SEPARATOR . 'patches' . DIRECTORY_SEPARATOR . $patchName;
        $ret = 0;
        passthru("cd src\\php-src && git apply \"$patch\"", $ret);
        if ($ret !== 0) {
            throw new Exception("failed to apply libxml2 2.15 backport for PHP {$phpVer}");
        }
    }
    
    public static function zstdAPCufix(bool $apcuEnabled = false) {
        if ($apcuEnabled) {
            // impossible
            throw new Exception("why use apcu on cli/micro SAPI");
            $replace = 'AC_DEFINE("HAVE_APCU_SUPPORT", 1, "APCu support");';
        } else {
            $replace = '';
        }
        $config_w32 = file_get_contents('src\php-src\ext\zstd\config.w32');
        $config_w32 = preg_replace('/if\s*\([^{]+\)\s*{\s*AC_DEFINE\s*\(\s*"HAVE_APCU[^)]+\)\s*;\s*}/m', $replace, $config_w32);
        file_put_contents('src\php-src\ext\zstd\config.w32', $config_w32);
    }

    public static function patchGD() {
		$avif = "";
		if (file_exists('src\php-src\ext\gd\libgd\gd_avif.c')) {
			$avif = 'gd_avif.c';
		}
        $config_w32 = <<<'JS'
// vim:ft=javascript

ARG_WITH("gd", "Bundled GD support", "yes,shared");

if (PHP_GD != "no") {
	if (CHECK_LIB("libjpeg_a.lib;libjpeg.lib", "gd", PHP_GD) &&
		CHECK_HEADER_ADD_INCLUDE("jpeglib.h", "CFLAGS_GD", PHP_GD + ";" + PHP_PHP_BUILD + "\\include")) {
		AC_DEFINE("HAVE_LIBJPEG", 1, "JPEG support");
		AC_DEFINE("HAVE_GD_JPG", 1, "JPEG support");
	}
	if (CHECK_LIB("libpng_a.lib;libpng.lib", "gd", PHP_GD) &&
		(CHECK_HEADER_ADD_INCLUDE("png.h", "CFLAGS_GD", PHP_GD +  ";" + PHP_PHP_BUILD + "\\include\\libpng16") ||
		CHECK_HEADER_ADD_INCLUDE("png.h", "CFLAGS_GD", PHP_GD +  ";" + PHP_PHP_BUILD + "\\include\\libpng15") ||
		CHECK_HEADER_ADD_INCLUDE("png.h", "CFLAGS_GD", PHP_GD +  ";" + PHP_PHP_BUILD + "\\include\\libpng12"))) {
		AC_DEFINE("HAVE_LIBPNG", 1, "PNG support");
		AC_DEFINE("HAVE_GD_PNG", 1, "PNG support");
	}
	if (CHECK_LIB("libXpm_a.lib", "gd", PHP_GD) &&
		CHECK_HEADER_ADD_INCLUDE("xpm.h", "CFLAGS_GD", PHP_GD + ";" + PHP_PHP_BUILD + "\\include\\X11")) {
		AC_DEFINE("HAVE_LIBXPM", 1, "XPM support");
		AC_DEFINE("HAVE_GD_XPM", 1, "XPM support");
	}
	if (CHECK_LIB("libfreetype_a.lib;libfreetype.lib", "gd", PHP_GD) &&
		CHECK_HEADER_ADD_INCLUDE("ft2build.h", "CFLAGS_GD", PHP_GD + ";" + PHP_PHP_BUILD + "\\include\\freetype2;" + PHP_PHP_BUILD + "\\include\\freetype")) {
		AC_DEFINE("HAVE_LIBFREETYPE", 1, "FreeType support");
		AC_DEFINE("HAVE_GD_FREETYPE", 1, "FreeType support");
	}
	if ((CHECK_LIB("libiconv_a.lib;libiconv.lib", "gd", PHP_GD) || CHECK_LIB("iconv_a.lib;iconv.lib", "gd", PHP_GD)) &&
		CHECK_HEADER_ADD_INCLUDE("iconv.h", "CFLAGS_GD", PHP_GD)) {
		AC_DEFINE("HAVE_LIBICONV", 1, "Iconv support");
	}
	if (CHECK_LIB("zlib_a.lib;zlib.lib", "gd", PHP_GD) &&
		(PHP_ZLIB_SHARED && CHECK_LIB("zlib.lib", "gd", PHP_GD))) {
		AC_DEFINE("HAVE_LIBZ", 1, "Zlib support");
	}
	if ((CHECK_LIB("libwebp_a.lib", "gd", PHP_GD) || CHECK_LIB("libwebp.lib", "gd", PHP_GD)) &&
		CHECK_HEADER_ADD_INCLUDE("decode.h", "CFLAGS_GD", PHP_GD + ";" + PHP_PHP_BUILD + "\\include\\webp") &&
		CHECK_HEADER_ADD_INCLUDE("encode.h", "CFLAGS_GD", PHP_GD + ";" + PHP_PHP_BUILD + "\\include\\webp")) {
		AC_DEFINE("HAVE_LIBWEBP", 1, "WebP support");
		AC_DEFINE("HAVE_GD_WEBP", 1, "WebP support");
	}
	if (CHECK_LIB("avif_a.lib", "gd", PHP_GD) &&
		CHECK_LIB("aom_a.lib", "gd", PHP_GD) &&
		CHECK_HEADER_ADD_INCLUDE("avif.h", "CFLAGS_GD", PHP_GD + ";" + PHP_PHP_BUILD + "\\include\\avif")) {
		ADD_FLAG("CFLAGS_GD", "/D HAVE_LIBAVIF /D HAVE_GD_AVIF");
	} else if (CHECK_LIB("avif.lib", "gd", PHP_GD) &&
		CHECK_HEADER_ADD_INCLUDE("avif.h", "CFLAGS_GD", PHP_GD + ";" + PHP_PHP_BUILD + "\\include\\avif")) {
		ADD_FLAG("CFLAGS_GD", "/D HAVE_LIBAVIF /D HAVE_GD_AVIF");
	}
	CHECK_LIB("User32.lib", "gd", PHP_GD);
	CHECK_LIB("Gdi32.lib", "gd", PHP_GD);

	EXTENSION("gd", "gd.c", PHP_OPENSSL_SHARED, "-Iext/gd/libgd");
	ADD_SOURCES("ext/gd/libgd", "gd.c \
		gdcache.c gdfontg.c gdfontl.c gdfontmb.c gdfonts.c gdfontt.c \
		gdft.c gd_gd2.c gd_gd.c gd_gif_in.c gd_gif_out.c gdhelpers.c gd_io.c gd_io_dp.c \
		gd_io_file.c gd_io_ss.c gd_jpeg.c gdkanji.c gd_png.c gd_ss.c \
		gdtables.c gd_topal.c gd_wbmp.c gdxpm.c wbmp.c gd_xbm.c gd_security.c gd_transform.c \
		gd_filter.c {$pixelate}gd_rotate.c gd_color_match.c gd_webp.c {$avif} \
		gd_crop.c gd_interpolation.c gd_matrix.c gd_bmp.c gd_tga.c", "gd");
	AC_DEFINE('HAVE_LIBGD', 1, 'GD support');
	AC_DEFINE('HAVE_GD_BUNDLED', 1, "Bundled GD");
	AC_DEFINE('HAVE_GD_BMP', 1, "BMP support");
	AC_DEFINE('HAVE_GD_TGA', 1, "TGA support");
	ADD_FLAG("CFLAGS_GD", " \
/D PHP_GD_EXPORTS=1 \
/D HAVE_GD_GET_INTERPOLATION \
	");
	if (ICC_TOOLSET) {
		ADD_FLAG("LDFLAGS_GD", "/nodefaultlib:libcmt");
	}

	PHP_INSTALL_HEADERS("", "ext/gd ext/gd/libgd" );
}
JS;
	$pixelate = file_exists('src\php-src\ext\gd\libgd\gd_pixelate.c') ? 'gd_pixelate.c ' : '';
	$config_w32 = str_replace('{$avif}', $avif, $config_w32);
	$config_w32 = str_replace('{$pixelate}', $pixelate, $config_w32);
    file_put_contents('src\php-src\ext\gd\config.w32', $config_w32);

    $gd_interpolation_c = file_get_contents('src\php-src\ext\gd\libgd\gd_interpolation.c');
    $gd_interpolation_c = preg_replace('/#\s*include\s*<emmintrin.h>\s*/', '', $gd_interpolation_c);
    file_put_contents('src\php-src\ext\gd\libgd\gd_interpolation.c', $gd_interpolation_c);

	// for php older than 82: https://github.com/php/php-src/commit/243966177e39eb71822935042c3f13fa6c5b9eed
	$gdft_c = file_get_contents('src\php-src\ext\gd\libgd\gdft.c');
	$gdft_c = str_replace('MSWIN32', '_WIN32', $gdft_c);
	file_put_contents('src\php-src\ext\gd\libgd\gdft.c', $gdft_c);
    }
}
