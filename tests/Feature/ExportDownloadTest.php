<?php

use App\Enums\ExportStatus;
use App\Models\Deck;
use App\Models\Export;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

test('authorized user can download an export', function () {
    Storage::fake('private');

    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create();

    $export = Export::factory()
        ->for($user)
        ->for($deck)
        ->state([
            'status' => ExportStatus::Ready,
            'apkg_path' => 'exports/'.$user->id.'/sample.apkg',
        ])
        ->create();

    Storage::disk('private')->put($export->apkg_path, 'apkg-data');

    $this->actingAs($user);

    $url = URL::temporarySignedRoute(
        'exports.download',
        now()->addMinutes(5),
        ['export' => $export->id]
    );

    $this->get($url)->assertSuccessful();
});

test('other users cannot download someone elses export', function () {
    Storage::fake('private');

    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $deck = Deck::factory()->for($owner)->create();

    $export = Export::factory()
        ->for($owner)
        ->for($deck)
        ->state([
            'status' => ExportStatus::Ready,
            'apkg_path' => 'exports/'.$owner->id.'/sample.apkg',
        ])
        ->create();

    Storage::disk('private')->put($export->apkg_path, 'apkg-data');

    $this->actingAs($otherUser);

    $url = URL::temporarySignedRoute(
        'exports.download',
        now()->addMinutes(5),
        ['export' => $export->id]
    );

    $this->get($url)->assertForbidden();
});
