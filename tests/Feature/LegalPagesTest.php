<?php

test('terms page is publicly accessible', function () {
    $response = $this->get(route('legal.terms'));

    $response->assertStatus(200);
    $response->assertSee('xaCare');
});

test('privacy page is publicly accessible', function () {
    $response = $this->get(route('legal.privacy'));

    $response->assertStatus(200);
    $response->assertSee('xaCare');
});
