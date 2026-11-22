<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'ui' => [
        'generate' => 'Wygeneruj',
        'translate' => 'Przetłumacz',
        'dialog_title' => 'Propozycja AI: :field',
        'translate_title' => 'Tłumaczenie: :field',
        'working' => 'Piszę…',
        'accept' => 'Użyj tego tekstu',
        'cancel' => 'Anuluj',
        'retry' => 'Spróbuj ponownie',
        'check' => 'Przeczytaj przed zapisaniem: AI się myli. Nic nie zostanie zapisane, dopóki nie klikniesz Zapisz.',
        'by' => 'Napisał :provider',
        'error' => 'AI nie odpowiedziała. Spróbuj później.',
        'demo_notice' => 'To demo jest publiczne: nie wpisuj danych osobowych, cudzych danych ani niczego prywatnego.',
    ],
    'help' => [
        'open' => 'Zapytaj asystenta',
        'title' => 'Asystent',
        'hello' => 'Cześć! Znam przewodniki Atrium i mogę zajrzeć do Twoich sekcji. Co chcesz wiedzieć?',
        'placeholder' => 'Zapytaj o panel lub swoje treści…',
        'send' => 'Wyślij',
        'thinking' => 'Myślę…',
        'note' => 'Tylko czytam i niczego nie zmieniam. Odpowiedzi mogą być błędne.',
        'clear' => 'Nowa rozmowa',
        'close' => 'Zamknij',
        'error' => 'Nie udało się odpowiedzieć. Spróbuj później.',
    ],
    'errors' => [
        'no_ai' => 'To pole nie ma generowania przez AI.',
        'no_image' => 'Najpierw prześlij obraz.',
        'no_source' => 'Najpierw wypełnij nazwę lub tekst: nie ma z czego pisać.',
        'not_translatable' => 'To pole nie ma tłumaczeń.',
        'not_configured' => 'AI nie jest skonfigurowana: żaden dostawca nie ma klucza.',
        'busy' => 'Wszyscy dostawcy AI są zajęci lub przekroczyli darmowy limit. Spróbuj za minutę.',
        'failed' => 'Dostawca AI nie odpowiedział. Spróbuj później.',
        'demo_limit' => 'Dzienny limit AI w demo został wyczerpany. Spróbuj jutro.',
    ],
];
