<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

class ImageCompressor
{
    /**
     * Reduce un logo subido a WebP, limitando su lado mas largo, para no
     * llenar el almacenamiento con imagenes de alta resolucion innecesarias.
     */
    public static function compressToWebp(UploadedFile $file, int $maxDimension = 512, int $quality = 82): string
    {
        $manager = new ImageManager(new Driver);

        $image = $manager->decodePath($file->getRealPath())
            ->scaleDown($maxDimension, $maxDimension);

        return (string) $image->encode(new WebpEncoder(quality: $quality));
    }
}
