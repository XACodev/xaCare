<?php

test('el sidebar de plataforma no usa colores indigo/zinc puro', function () {
    $blade = file_get_contents(resource_path('views/components/layouts/platform/sidebar.blade.php'));

    expect($blade)->not->toContain('indigo');
});
