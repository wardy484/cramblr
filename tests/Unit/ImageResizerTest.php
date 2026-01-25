<?php

use App\Services\Extraction\ImageResizer;

it('shrinks images to the max size', function () {
    $width = 3000;
    $height = 1800;

    $image = imagecreatetruecolor($width, $height);
    $color = imagecolorallocate($image, 50, 100, 150);
    imagefilledrectangle($image, 0, 0, $width, $height, $color);

    ob_start();
    imagejpeg($image, null, 90);
    $binary = ob_get_clean();

    imagedestroy($image);

    expect($binary)->toBeString();

    $resizer = new ImageResizer();
    $resized = $resizer->resizeToMax($binary);
    $size = getimagesizefromstring($resized);

    expect($size)->not->toBeFalse();
    expect(max($size[0], $size[1]))->toBeLessThanOrEqual(1400);
});
