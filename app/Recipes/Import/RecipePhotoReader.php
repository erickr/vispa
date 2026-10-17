<?php

namespace App\Recipes\Import;

use GdImage;

/**
 * Turns an uploaded photo of a recipe (a cookbook page, a handwritten card) into a RecipeSource.
 * Every photo is re-encoded as a JPEG: that turns it the way the camera held it, keeps it under
 * the API's per-image size limit, and leaves its metadata (GPS position included) behind.
 */
class RecipePhotoReader
{
    /** The longest edge the model reads at full detail; anything larger is scaled down to it. */
    public const MAX_EDGE = 2576;

    private const JPEG_QUALITY = 85;

    public function read(string $bytes): RecipeSource
    {
        $image = @imagecreatefromstring($bytes);

        if (! $image instanceof GdImage) {
            throw new RecipeImportException(__('recipe.import.errors.unreadable_photo'));
        }

        $image = $this->scaleDown($this->upright($image, $bytes));

        ob_start();
        imagejpeg($image, null, self::JPEG_QUALITY);

        return RecipeSource::fromImage((string) ob_get_clean(), 'image/jpeg');
    }

    /** Phones store a photo as the sensor saw it and say in EXIF which way is up. */
    private function upright(GdImage $image, string $bytes): GdImage
    {
        $exif = function_exists('exif_read_data')
            ? @exif_read_data('data://image/jpeg;base64,'.base64_encode($bytes))
            : false;

        $rotated = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated instanceof GdImage ? $rotated : $image;
    }

    private function scaleDown(GdImage $image): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);

        if ($longest <= self::MAX_EDGE) {
            return $image;
        }

        $scale = self::MAX_EDGE / $longest;
        $scaled = imagescale($image, (int) round($width * $scale), (int) round($height * $scale));

        return $scaled instanceof GdImage ? $scaled : $image;
    }
}
