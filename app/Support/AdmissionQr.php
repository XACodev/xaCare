<?php

namespace App\Support;

use App\Models\Admission;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;

final class AdmissionQr
{
    public static function generateToken(): string
    {
        return Str::random(64);
    }

    public static function svg(Admission $admission, int $size = 240): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 2),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);

        return $writer->writeString($admission->qrUrl());
    }
}
