<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests cannot access horizon', function () {
    $this->get('/horizon')->assertForbidden();
});

test('wardy484 can access horizon', function () {
    $user = User::factory()->create(['email' => 'wardy484@gmail.com']);

    $this->actingAs($user)->get('/horizon')->assertOk();
});

test('other authenticated users cannot access horizon', function () {
    $user = User::factory()->create(['email' => 'other@example.com']);

    $this->actingAs($user)->get('/horizon')->assertForbidden();
});
