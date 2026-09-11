<?php

use App\Models\Hospital;
use App\Models\User;

test('el sidebar usa las clases de marca teal, no indigo', function () {
    $blade = file_get_contents(resource_path('views/components/layouts/app/sidebar.blade.php'));

    expect($blade)->not->toContain('bg-indigo-100');
    expect($blade)->not->toContain('bg-indigo-400');
    expect($blade)->not->toContain('border-indigo-600');
    expect($blade)->toContain('bg-mist');
    expect($blade)->toContain('text-accent');
});

test('el item activo del sidebar tiene bg-mist/text-accent aplicables sobre el nodo con data-current', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);

    $html = $this->actingAs($admin)->get('/patients')->assertOk()->getContent();

    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    $current = $xpath->query('//*[@data-current]')->item(0);
    expect($current)->not->toBeNull();

    // La clase debe vivir en un ancestro (selector con combinador descendiente [&_[data-current]]),
    // nunca en el propio elemento con data-current (ese selector [&[data-current]] es codigo muerto:
    // Flux pone data-current en el <a> hijo, no en el <nav> donde se aplicaba la clase antes del fix).
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

test('las etiquetas del sidebar en ingles tienen traduccion en es.json', function () {
    $hospital = Hospital::factory()->create();
    $admin = User::factory()->create(['hospital_id' => $hospital->id, 'role' => 'admin']);

    $this->actingAs($admin)->get('/dashboard')
        ->assertOk()
        ->assertDontSee('Payouts')
        ->assertDontSee('History')
        ->assertDontSee('General Settings')
        ->assertDontSee('Make Payment')
        ->assertDontSee('Payment History')
        ->assertDontSee('Procedure History');
});
