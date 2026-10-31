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
    use UnixUtilTrait;

    // Apple documents macOS 11-26.x as the deployment-target range for Xcode 26.x,
    // and arm64 cannot target anything older than 11.0. Keep this in sync with
    // the CMAKE_OSX_DEPLOYMENT_TARGET we hand to CMake below
    public const MACOSX_DEPLOYMENT_TARGET = '11.0';

    public static function getCpuCount(): int
    {
        exec('sysctl -n hw.ncpu', $output, $ret);
        if ($ret !== 0) {
            throw new Exception('Failed to get cpu count');
        }

        return (int) $output[0];
    }

    public static function getArchCFlags(string $arch): string
    {
        return '--target=' . static::getArchTarget($arch)
            . ' -mmacosx-version-min=' . static::MACOSX_DEPLOYMENT_TARGET;
    }

    public static function getArchLDFlags(string $arch): string
    {
        return '--target=' . static::getArchTarget($arch)
            . ' -mmacosx-version-min=' . static::MACOSX_DEPLOYMENT_TARGET;
    }

    private static function getArchTarget(string $arch): string
    {
        return match ($arch) {
            'x86_64' => 'x86_64-apple-darwin',
            'arm64', 'aarch64' => 'arm64-apple-darwin',
            default => throw new Exception('unsupported arch: ' . $arch),
        };
    }

    public static function rosettaAvailable(): bool
    {
        exec('arch -x86_64 /usr/bin/true 2>/dev/null', $output, $ret);
        return $ret === 0;
    }

    public static function rosettaSanityCheck(string $expectedSapi): void
    {
        Log::i('running sanity checks with Rosetta');
        self::runMicroBinary('hello.exe', '<?php echo "hello";', 'hello', 'micro failed sanity check');
        self::runMicroBinary('sapiName.exe', '<?php echo PHP_SAPI;', $expectedSapi, 'micro failed SAPI name check');
    }

    private static function runMicroBinary(string $file, string $code, string $expected, string $error): void
    {
        // remove the executable to avoid strange macos behavior on executable changed
        @unlink($file);
        file_put_contents(
            $file,
            file_get_contents('src/php-src/sapi/micro/micro.sfx') . $code
        );
        chmod($file, 0755);
        exec('arch -x86_64 ./' . $file, $output, $ret);
        if ($ret !== 0 || trim(implode('', $output)) !== $expected) {
            throw new Exception($error);
        }
    }
}
