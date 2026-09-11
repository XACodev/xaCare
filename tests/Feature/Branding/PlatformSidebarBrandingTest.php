<?php

use App\Models\User;

test('el sidebar de plataforma no usa colores indigo/zinc puro', function () {
    $blade = file_get_contents(resource_path('views/components/layouts/platform/sidebar.blade.php'));

    expect($blade)->not->toContain('indigo');
});

test('el sidebar de plataforma usa las clases de marca teal para items activos', function () {
    $blade = file_get_contents(resource_path('views/components/layouts/platform/sidebar.blade.php'));

    expect($blade)->toContain('bg-mist');
    expect($blade)->toContain('text-accent');
    expect($blade)->toContain('[&_[data-current]]:bg-mist');
    expect($blade)->toContain('[&_[data-current]]:text-accent');
});

test('el item activo del sidebar de plataforma tiene bg-mist/text-accent aplicables sobre el nodo con data-current', function () {
    $admin = User::factory()->withTwoFactor()->create(['is_platform_admin' => true, 'hospital_id' => null]);

    $html = $this->actingAs($admin)->get(route('platform.hospitals.index'))->assertOk()->getContent();

    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    $current = $xpath->query('//*[@data-current]')->item(0);
    expect($current)->not->toBeNull();

    expect($current->getAttribute('class'))->not->toContain('[&_[data-current]]');
    expect($current->getAttribute('class'))->not->toContain('[&[data-current]]');

    $ancestorHasClass = false;
    $node = $current->parentNode;
    while ($node instanceof DOMElement) {
        if (str_contains($node->getAttribute('class'), '[&_[data-current]]:bg-mist')
            && str_contains($node->getAttribute('class'), '[&_[data-current]]:text-accent')) {
            $ancestorHasClass = true;
            break;
        }
        $node = $node->parentNode;
    }

    expect($ancestorHasClass)->toBeTrue();
});
