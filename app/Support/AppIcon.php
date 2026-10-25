<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * The app's favicon and home-screen icons, made from one uploaded image.
 * `app_icon_version` is set while they exist and busts browser caches when they change.
 */
final class AppIcon
{
    public const SIZES = [16, 32, 180, 192, 512];

    private const DIRECTORY = 'branding';

    public static function version(): ?string
    {
        try {
            return Setting::getSetting('app_icon_version') ?: null;
        } catch (QueryException) {
            // Before installation there is no settings table yet.
            return null;
        }
    }

    public static function url(int $size): string
    {
        return '/storage/'.self::path($size).'?v='.self::version();
    }

    /**
     * Crops the image to its centred square and stores it in every size.
     */
    public static function store(string $imageBytes): void
    {
        $source = @imagecreatefromstring($imageBytes);

        if ($source === false) {
            throw new InvalidArgumentException('The app icon is not a readable image.');
        }

        $side = min(imagesx($source), imagesy($source));
        $x = intdiv(imagesx($source) - $side, 2);
        $y = intdiv(imagesy($source) - $side, 2);

        foreach (self::SIZES as $size) {
            $icon = imagecreatetruecolor($size, $size);
            imagealphablending($icon, false);
            imagesavealpha($icon, true);
            imagefill($icon, 0, 0, imagecolorallocatealpha($icon, 0, 0, 0, 127));
            imagecopyresampled($icon, $source, 0, 0, $x, $y, $size, $size, $side, $side);

            ob_start();
            imagepng($icon);
            Storage::disk('public')->put(self::path($size), ob_get_clean());
        }

        Setting::setSetting('app_icon_version', (string) now()->timestamp);
    }

    public static function remove(): void
    {
        Storage::disk('public')->delete(array_map(self::path(...), self::SIZES));
        Setting::setSetting('app_icon_version', '');
    }

    private static function path(int $size): string
    {
        return self::DIRECTORY."/app-icon-{$size}.png";
    }
}
