<?php

use App\Support\ImageCompressor;
use Illuminate\Http\UploadedFile;

test('compresses an uploaded image to webp', function () {
    $file = UploadedFile::fake()->image('logo.png', 1200, 1200);

    $webp = ImageCompressor::compressToWebp($file);

    expect(substr($webp, 8, 4))->toBe('WEBP');
});

test('scales down images larger than the max dimension', function () {
    $file = UploadedFile::fake()->image('logo.png', 2000, 1000);

    $webp = ImageCompressor::compressToWebp($file, maxDimension: 512);

    $size = getimagesizefromstring($webp);

    expect($size[0])->toBeLessThanOrEqual(512)
        ->and($size[1])->toBeLessThanOrEqual(512);
});

test('does not upscale images smaller than the max dimension', function () {
    $file = UploadedFile::fake()->image('logo.png', 100, 80);

    $webp = ImageCompressor::compressToWebp($file, maxDimension: 512);

    $size = getimagesizefromstring($webp);

    expect($size[0])->toBe(100)
        ->and($size[1])->toBe(80);
});
