<?php

namespace App\Support;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;

class QrCodeSvg
{
    public static function inline(string $data, int $size = 160): string
    {
        $result = (new Builder(
            writer: new SvgWriter(),
            writerOptions: [
                SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true,
            ],
            data: $data,
            size: $size,
            margin: 0,
        ))->build();

        return $result->getString();
    }
}
