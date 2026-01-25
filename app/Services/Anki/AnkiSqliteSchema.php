<?php

namespace App\Services\Anki;

use PDO;

class AnkiSqliteSchema
{
    public function create(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE col (
            id integer primary key,
            crt integer,
            mod integer,
            scm integer,
            ver integer,
            dty integer,
            usn integer,
            ls integer,
            conf text,
            models text,
            decks text,
            dconf text,
            tags text
        )');

        $pdo->exec('CREATE TABLE notes (
            id integer primary key,
            guid text,
            mid integer,
            mod integer,
            usn integer,
            tags text,
            flds text,
            sfld integer,
            csum integer,
            flags integer,
            data text
        )');

        $pdo->exec('CREATE TABLE cards (
            id integer primary key,
            nid integer,
            did integer,
            ord integer,
            mod integer,
            usn integer,
            type integer,
            queue integer,
            due integer,
            ivl integer,
            factor integer,
            reps integer,
            lapses integer,
            left integer,
            odue integer,
            odid integer,
            flags integer,
            data text
        )');

        $pdo->exec('CREATE INDEX ix_notes_csum ON notes(csum)');
        $pdo->exec('CREATE INDEX ix_cards_nid ON cards(nid)');
        $pdo->exec('CREATE INDEX ix_cards_did ON cards(did)');
    }
}
