<?php

use App\Enums\CardStatus;
use App\Models\Card;
use App\Models\Deck;
use App\Models\User;
use App\Services\Anki\AnkiPackageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('it builds a deterministic anki package', function () {
    $user = User::factory()->create();
    $deck = Deck::factory()->for($user)->create();
    $cards = Card::factory()
        ->count(2)
        ->for($user)
        ->for($deck)
        ->state(['status' => CardStatus::Approved])
        ->create();

    $builder = app(AnkiPackageBuilder::class);

    $outputDir = storage_path('app/private/tests');
    File::ensureDirectoryExists($outputDir);

    $pathOne = $outputDir.'/'.Str::uuid().'.apkg';
    $pathTwo = $outputDir.'/'.Str::uuid().'.apkg';

    $builder->build($deck, $cards, $pathOne);
    $builder->build($deck, $cards, $pathTwo);

    expect(file_exists($pathOne))->toBeTrue()
        ->and(file_exists($pathTwo))->toBeTrue();

    $hashOne = hash_file('sha256', $pathOne);
    $hashTwo = hash_file('sha256', $pathTwo);

    expect($hashOne)->toBe($hashTwo);

    $zip = new \ZipArchive();
    $zip->open($pathOne);

    expect($zip->locateName('collection.anki2'))->not()->toBeFalse()
        ->and($zip->locateName('media'))->not()->toBeFalse();

    $zip->close();

    @unlink($pathOne);
    @unlink($pathTwo);
});
