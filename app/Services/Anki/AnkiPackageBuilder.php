<?php

namespace App\Services\Anki;

use App\Models\Deck;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PDO;
use ZipArchive;

class AnkiPackageBuilder
{
    private const DEFAULT_TIMESTAMP = 0;

    public function __construct(private readonly AnkiSqliteSchema $schema)
    {
    }

    /**
     * @param iterable<int, \App\Models\Card> $cards
     */
    public function build(Deck $deck, iterable $cards, string $outputPath): void
    {
        $tempDir = storage_path('app/private/anki/'.Str::uuid());

        File::ensureDirectoryExists($tempDir);

        $dbPath = $tempDir.'/collection.anki2';

        $pdo = new PDO('sqlite:'.$dbPath);
        $this->schema->create($pdo);

        $deckId = $this->deterministicId($deck->id);
        $modelId = 1;

        $this->insertCollection($pdo, $deck, $deckId, $modelId);
        $this->insertNotesAndCards($pdo, $cards, $deckId, $modelId);

        $mediaPath = $tempDir.'/media';
        File::put($mediaPath, '{}');

        touch($dbPath, self::DEFAULT_TIMESTAMP);
        touch($mediaPath, self::DEFAULT_TIMESTAMP);

        $zipPath = $tempDir.'/export.apkg';
        $this->zipPackage($zipPath, $dbPath, $mediaPath);

        File::ensureDirectoryExists(dirname($outputPath));
        File::move($zipPath, $outputPath);

        File::deleteDirectory($tempDir);
    }

    private function insertCollection(PDO $pdo, Deck $deck, int $deckId, int $modelId): void
    {
        $models = [
            $modelId => [
                'id' => $modelId,
                'name' => 'Basic',
                'type' => 0,
                'mod' => self::DEFAULT_TIMESTAMP,
                'usn' => -1,
                'did' => $deckId,
                'sortf' => 0,
                'latexPre' => '',
                'latexPost' => '',
                'flds' => [
                    ['name' => 'Front', 'ord' => 0],
                    ['name' => 'Back', 'ord' => 1],
                ],
                'tmpls' => [
                    [
                        'name' => 'Card 1',
                        'ord' => 0,
                        'qfmt' => '{{Front}}',
                        'afmt' => '{{FrontSide}}<hr id=answer>{{Back}}',
                    ],
                ],
                'req' => [[0, 'all', [0]]],
                'css' => '.card { font-family: arial; font-size: 20px; text-align: left; color: black; background-color: white; }',
                'tags' => [],
            ],
        ];

        $decks = [
            $deckId => [
                'id' => $deckId,
                'name' => $deck->name,
                'desc' => $deck->description ?? '',
                'dyn' => 0,
                'conf' => 1,
                'mod' => self::DEFAULT_TIMESTAMP,
                'usn' => -1,
                'collapsed' => false,
                'newToday' => [0, 0],
                'revToday' => [0, 0],
                'lrnToday' => [0, 0],
                'timeToday' => [0, 0],
                'extendNew' => 0,
                'extendRev' => 0,
            ],
        ];

        $dconf = [
            1 => [
                'id' => 1,
                'name' => 'Default',
                'usn' => 0,
                'new' => ['perDay' => 20, 'delays' => [1, 10], 'ints' => [1, 4, 7]],
                'rev' => ['perDay' => 100, 'ease4' => 1.3, 'maxIvl' => 36500, 'minSpace' => 1],
                'lapse' => ['delays' => [10], 'mult' => 0, 'minInt' => 1, 'leechFails' => 8, 'leechAction' => 0],
                'maxTaken' => 60,
                'timer' => 0,
                'autoplay' => true,
                'replayq' => true,
                'mod' => self::DEFAULT_TIMESTAMP,
            ],
        ];

        $stmt = $pdo->prepare('INSERT INTO col (id, crt, mod, scm, ver, dty, usn, ls, conf, models, decks, dconf, tags) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

        $stmt->execute([
            1,
            self::DEFAULT_TIMESTAMP,
            self::DEFAULT_TIMESTAMP,
            self::DEFAULT_TIMESTAMP,
            11,
            0,
            0,
            0,
            json_encode(new \stdClass(), JSON_THROW_ON_ERROR),
            json_encode($models, JSON_THROW_ON_ERROR),
            json_encode($decks, JSON_THROW_ON_ERROR),
            json_encode($dconf, JSON_THROW_ON_ERROR),
            json_encode(new \stdClass(), JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * @param iterable<int, \App\Models\Card> $cards
     */
    private function insertNotesAndCards(PDO $pdo, iterable $cards, int $deckId, int $modelId): void
    {
        $noteStmt = $pdo->prepare('INSERT INTO notes (id, guid, mid, mod, usn, tags, flds, sfld, csum, flags, data) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $cardStmt = $pdo->prepare('INSERT INTO cards (id, nid, did, ord, mod, usn, type, queue, due, ivl, factor, reps, lapses, left, odue, odid, flags, data) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

        $due = 1;

        foreach ($cards as $card) {
            $front = (string) $card->front;
            $back = (string) $card->back;
            $guid = $this->deterministicGuid($front, $back, $card->deck_id);
            $noteId = $this->deterministicId($guid.'note');
            $cardId = $this->deterministicId($guid.'card');

            $tags = Arr::wrap($card->tags);
            sort($tags);
            $tagsString = $tags === [] ? '' : ' '.implode(' ', $tags).' ';

            $fields = $front."\x1f".$back;
            $sfld = $front;
            $csum = hexdec(substr(sha1($sfld), 0, 8));

            $noteStmt->execute([
                $noteId,
                $guid,
                $modelId,
                self::DEFAULT_TIMESTAMP,
                -1,
                $tagsString,
                $fields,
                $sfld,
                $csum,
                0,
                '',
            ]);

            $cardStmt->execute([
                $cardId,
                $noteId,
                $deckId,
                0,
                self::DEFAULT_TIMESTAMP,
                -1,
                0,
                0,
                $due,
                0,
                2500,
                0,
                0,
                0,
                0,
                0,
                0,
                '',
            ]);

            $due++;
        }
    }

    private function zipPackage(string $zipPath, string $dbPath, string $mediaPath): void
    {
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to create Anki package.');
        }

        $zip->addFile($dbPath, 'collection.anki2');
        $zip->addFile($mediaPath, 'media');

        $zip->setMtimeName('collection.anki2', self::DEFAULT_TIMESTAMP);
        $zip->setMtimeName('media', self::DEFAULT_TIMESTAMP);
        $zip->close();
    }

    private function deterministicGuid(string $front, string $back, string $deckId): string
    {
        return hash('sha1', $front.'|'.$back.'|'.$deckId);
    }

    private function deterministicId(string $seed): int
    {
        $hash = substr(sha1($seed), 0, 12);
        $value = hexdec($hash);

        return $value === 0 ? 1 : $value;
    }
}
