<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'settings' => [
        'module' => 'AI',
        'description' => 'Jak wygląda asystent AI w rogu panelu.',
        'playful' => 'Wesoły asystent',
        'playful_hint' => 'Brwi, uśmiech i drobne przebrania pokazują, czym zajmuje się asystent. Wyłączone: spokojny łuk. W żadnym wariancie nie wyskakuje ani nie odzywa się sam.',
    ],
    'ui' => [
        'generate' => 'Wygeneruj',
        'translate' => 'Przetłumacz',
        'improve' => 'Popraw',
        'improve_selection' => 'Popraw zaznaczenie',
        'dialog_title' => 'Propozycja AI: :field',
        'translate_title' => 'Tłumaczenie: :field',
        'improve_title' => 'Poprawiony tekst: :field',
        'menu' => 'Pomoc AI',
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
        'mood' => [
            'thinking' => 'Asystent myśli',
            'happy' => 'Asystent odpowiedział',
            'sorry' => 'Asystent nie zdołał odpowiedzieć',
            'search' => 'Asystent przegląda wpisy',
            'read' => 'Asystent czyta poradniki',
            'write' => 'AI pisze tekst',
        ],
    ],
    'errors' => [
        'no_ai' => 'To pole nie ma generowania przez AI.',
        'no_image' => 'Najpierw prześlij obraz.',
        'no_source' => 'Najpierw wypełnij nazwę lub tekst: nie ma z czego pisać.',
        'too_long' => 'Tekst jest za długi, by poprawić go za jednym razem. Popraw go częściami.',
        'not_translatable' => 'To pole nie ma tłumaczeń.',
        'not_configured' => 'AI nie jest skonfigurowana: żaden dostawca nie ma klucza.',
        'busy' => 'Wszyscy dostawcy AI są zajęci lub przekroczyli darmowy limit. Spróbuj za minutę.',
        'failed' => 'Dostawca AI nie odpowiedział. Spróbuj później.',
        'demo_limit' => 'Dzienny limit AI w demo został wyczerpany. Spróbuj jutro.',
    ],
];
