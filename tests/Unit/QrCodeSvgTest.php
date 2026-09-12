<?php

use App\Support\QrCodeSvg;

test('genera un svg valido a partir de una url', function () {
    $svg = QrCodeSvg::inline('https://xacare.test/quotes/abc123/verify');

    expect($svg)->toStartWith('<svg');
    expect($svg)->toContain('</svg>');
});
