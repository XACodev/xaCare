<?php

test('el icono de marca usa la geometria y colores reales de xaCare', function () {
    $svg = file_get_contents(resource_path('views/components/app-logo-icon.blade.php'));

    expect($svg)->toContain('viewBox="0 0 32 32"');
    expect($svg)->toContain('#0F766E');
    expect($svg)->toContain('#2DD4BF');
    expect($svg)->not->toContain('c2pa');
    expect($svg)->not->toContain('Claude');
    expect($svg)->not->toContain('Anthropic');
});
