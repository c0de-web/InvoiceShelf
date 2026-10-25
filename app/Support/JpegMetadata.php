<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Removes the metadata a camera writes into a JPEG (GPS location, device,
 * timestamps, XMP, IPTC) without decoding the image, so there is no quality
 * loss and no memory spike on large phone photos.
 *
 * Only the EXIF orientation survives, rewritten as the sole tag, because
 * browsers and viewers need it to show a sideways photo upright.
 */
final class JpegMetadata
{
    /** APP1 holds EXIF and XMP; APP13 holds Photoshop/IPTC data. */
    private const STRIPPED_MARKERS = [0xE1, 0xED];

    /**
     * Writes a copy of the JPEG at `$path` without metadata and returns its
     * path, or null when the file is not a JPEG or carries no metadata.
     */
    public static function stripToTemporaryFile(string $path): ?string
    {
        if (@exif_imagetype($path) !== IMAGETYPE_JPEG) {
            return null;
        }

        $bytes = file_get_contents($path);
        $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);

        [$kept, $removedAny] = self::withoutMetadataSegments($bytes);

        if (! $removedAny) {
            return null;
        }

        $clean = tempnam(sys_get_temp_dir(), 'jpeg-meta');
        file_put_contents($clean, "\xFF\xD8".self::orientationSegment($orientation).$kept);

        return $clean;
    }

    /**
     * Every segment after the SOI marker except the metadata ones, and
     * whether any were removed. Everything from the start-of-scan marker on is
     * image data and is copied as is.
     *
     * @return array{0: string, 1: bool}
     */
    private static function withoutMetadataSegments(string $bytes): array
    {
        $kept = '';
        $removedAny = false;
        $offset = 2;
        $length = strlen($bytes);

        while ($offset + 4 <= $length) {
            if ($bytes[$offset] !== "\xFF") {
                throw new InvalidArgumentException('Malformed JPEG: expected a marker.');
            }

            $marker = ord($bytes[$offset + 1]);

            if ($marker === 0xDA) {
                return [$kept.substr($bytes, $offset), $removedAny];
            }

            $segmentLength = unpack('n', substr($bytes, $offset + 2, 2))[1] + 2;
            $segment = substr($bytes, $offset, $segmentLength);

            if (in_array($marker, self::STRIPPED_MARKERS, true)) {
                $removedAny = true;
            } else {
                $kept .= $segment;
            }

            $offset += $segmentLength;
        }

        throw new InvalidArgumentException('Malformed JPEG: no image data found.');
    }

    /**
     * A minimal APP1 EXIF segment holding only the orientation tag, or nothing
     * for the default orientation.
     */
    private static function orientationSegment(int $orientation): string
    {
        if ($orientation < 2 || $orientation > 8) {
            return '';
        }

        $tiff = 'II'.pack('vV', 42, 8)
            .pack('v', 1)
            .pack('vvVvv', 0x0112, 3, 1, $orientation, 0)
            .pack('V', 0);
        $exif = "Exif\0\0".$tiff;

        return "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;
    }
}
