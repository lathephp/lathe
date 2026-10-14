<?php

namespace Lathe\Permission\Tests\Discovery;

/**
 * The vendor/composer/installed.json an app has after `composer require lathe/permission`.
 * Composer copies each package's manifest, extra block included, into that file, so this
 * embeds the package's real composer.json: dropping extra.lathe.plugin fails discovery.
 */
final class InstalledJson
{
    /**
     * This package's composer.json, decoded.
     *
     * @return array<string, mixed>
     */
    public static function packageManifest(): array
    {
        /** @var array<string, mixed> */
        return json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Write a vendor folder whose installed.json lists the other packages and then this one,
     * and return its path. The caller removes it with forget().
     *
     * @param  list<array<string, mixed>>  $otherPackages
     */
    public static function write(array $otherPackages = []): string
    {
        $vendor = sys_get_temp_dir().'/lathe-permission-vendor-'.bin2hex(random_bytes(6));
        mkdir($vendor.'/composer', 0777, true);

        file_put_contents($vendor.'/composer/installed.json', json_encode([
            'packages' => [...$otherPackages, self::packageManifest()],
            'dev' => true,
        ], JSON_THROW_ON_ERROR));

        return $vendor;
    }

    public static function forget(string $vendor): void
    {
        @unlink($vendor.'/composer/installed.json');
        @rmdir($vendor.'/composer');
        @rmdir($vendor);
    }
}
