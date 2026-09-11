<?php

test('app.css define los tokens de marca xaCare', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('--color-accent: #0F766E;');
    expect($css)->toContain('--color-mist: #E6F2F0;');
    expect($css)->toContain('--color-canvas: #F4F6F5;');
    expect($css)->toContain('--color-urgent: #B4321F;');
    expect($css)->toContain('--color-pending: #B45309;');
    expect($css)->toContain('--color-done: #15803D;');
    expect($css)->toContain('--color-zinc-900: #12201F;');
});
