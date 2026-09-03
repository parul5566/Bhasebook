<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns the landing page', function () {
    $response = $this->get('/welcome');

    $response->assertStatus(200);
});
