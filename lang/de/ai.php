<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'ui' => [
        'generate' => 'Generieren',
        'translate' => 'Übersetzen',
        'dialog_title' => 'KI-Vorschlag: :field',
        'translate_title' => 'Übersetzung: :field',
        'working' => 'Schreibe…',
        'accept' => 'Text übernehmen',
        'cancel' => 'Abbrechen',
        'retry' => 'Noch einmal',
        'check' => 'Vor dem Speichern lesen: KI macht Fehler. Nichts wird gespeichert, bevor Sie auf Speichern klicken.',
        'by' => 'Geschrieben von :provider',
        'error' => 'Die KI hat nicht geantwortet. Versuchen Sie es später erneut.',
    ],
    'help' => [
        'open' => 'Den Assistenten fragen',
        'title' => 'Assistent',
        'hello' => 'Hallo! Ich kenne die Atrium-Anleitungen und kann in Ihre Bereiche schauen. Was möchten Sie wissen?',
        'placeholder' => 'Fragen Sie zum Admin-Bereich oder zu Ihren Inhalten…',
        'send' => 'Senden',
        'thinking' => 'Ich denke nach…',
        'note' => 'Ich lese nur und ändere nichts. Antworten können falsch sein.',
        'clear' => 'Neues Gespräch',
        'close' => 'Schließen',
        'error' => 'Ich konnte nicht antworten. Versuchen Sie es später erneut.',
    ],
    'errors' => [
        'no_ai' => 'Für dieses Feld gibt es keine KI-Generierung.',
        'no_image' => 'Laden Sie zuerst das Bild hoch.',
        'no_source' => 'Füllen Sie zuerst den Namen oder den Text aus: Es gibt nichts, woraus geschrieben werden kann.',
        'not_translatable' => 'Dieses Feld hat keine Übersetzungen.',
        'not_configured' => 'KI ist nicht eingerichtet: Kein Anbieter hat einen Schlüssel.',
        'busy' => 'Alle KI-Anbieter sind ausgelastet oder über ihrem kostenlosen Limit. Versuchen Sie es in einer Minute erneut.',
        'failed' => 'Der KI-Anbieter hat nicht geantwortet. Versuchen Sie es später erneut.',
    ],
];
