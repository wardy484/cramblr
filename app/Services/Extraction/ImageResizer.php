<?php

namespace App\Services\Extraction;

class ImageResizer
{
    public function resizeToMax(string $binary, int $maxSize = 1400, int $quality = 75): string
    {
        $image = imagecreatefromstring($binary);

        if ($image === false) {
            throw new \RuntimeException('Unable to decode image.');
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);

        if ($longest > $maxSize) {
            $ratio = $maxSize / $longest;
            $newWidth = (int) round($width * $ratio);
            $newHeight = (int) round($height * $ratio);

            $resized = imagescale($image, $newWidth, $newHeight, IMG_BICUBIC);

            if ($resized === false) {
                imagedestroy($image);
                throw new \RuntimeException('Unable to resize image.');
            }

            imagedestroy($image);
            $image = $resized;
        }

        ob_start();
        imagejpeg($image, null, $quality);
        $output = ob_get_clean();

        imagedestroy($image);

        if (!is_string($output)) {
            throw new \RuntimeException('Unable to encode resized image.');
        }

        return $output;
    }
}
