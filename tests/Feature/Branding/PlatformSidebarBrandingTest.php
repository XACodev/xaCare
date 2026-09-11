<?php

test('el sidebar de plataforma no usa colores indigo/zinc puro', function () {
    $blade = file_get_contents(resource_path('views/components/layouts/platform/sidebar.blade.php'));

    expect($blade)->not->toContain('indigo');
});

test('el sidebar de plataforma usa las clases de marca teal para items activos', function () {
    $blade = file_get_contents(resource_path('views/components/layouts/platform/sidebar.blade.php'));

    expect($blade)->toContain('bg-mist');
    expect($blade)->toContain('text-accent');
    expect($blade)->toContain('[&[data-current]]:bg-mist');
    expect($blade)->toContain('[&[data-current]]:text-accent');
});
