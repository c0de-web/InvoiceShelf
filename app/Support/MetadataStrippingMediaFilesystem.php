<?php

namespace App\Support;

use Spatie\MediaLibrary\MediaCollections\Filesystem;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The media library's filesystem, except that a JPEG loses its metadata
 * (above all its GPS location) before it is stored. Every upload passes
 * through here: avatars, company logos, expense receipts.
 *
 * The cleaned copy is written to a temporary file, so the source is never
 * modified; the media library may have been asked to preserve it.
 */
class MetadataStrippingMediaFilesystem extends Filesystem
{
    public function add(string $file, Media $media, ?string $targetFileName = null): bool
    {
        $clean = JpegMetadata::stripToTemporaryFile($file);

        if ($clean === null) {
            return parent::add($file, $media, $targetFileName);
        }

        try {
            if (! parent::add($clean, $media, $targetFileName ?? basename($file))) {
                return false;
            }

            // The media row was saved with the original file's size.
            $media->size = filesize($clean);
            $media->save();

            return true;
        } finally {
            @unlink($clean);
        }
    }
}
