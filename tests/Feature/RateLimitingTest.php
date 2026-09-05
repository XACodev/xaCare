<?php

use App\Models\Hospital;
use App\Models\HospitalInvitation;

test('la ruta de aceptación de invitación de hospital responde 429 tras exceder el límite', function () {
    $hospital = Hospital::factory()->create();
    [, $plainToken] = HospitalInvitation::generateFor($hospital->id, null, 'Test invite');

    $url = route('hospital-invitations.accept', ['token' => $plainToken]);

    for ($i = 0; $i < 10; $i++) {
        $this->get($url)->assertOk();
    }

    $this->get($url)->assertStatus(429);
});

test('la ruta de reset de password de Fortify responde 429 tras exceder el límite', function () {
    $url = route('password.email');

    for ($i = 0; $i < 5; $i++) {
        $this->post($url, ['email' => 'someone@example.com']);
    }

    $this->post($url, ['email' => 'someone@example.com'])->assertStatus(429);
});
