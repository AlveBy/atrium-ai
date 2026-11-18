<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'ui' => [
        'generate' => 'Genera',
        'translate' => 'Traduci',
        'dialog_title' => 'Proposta dell’IA: :field',
        'translate_title' => 'Traduzione: :field',
        'working' => 'Scrittura…',
        'accept' => 'Usa questo testo',
        'cancel' => 'Annulla',
        'retry' => 'Riprova',
        'check' => 'Rileggi prima di salvare: l’IA sbaglia. Non viene salvato nulla finché non premi Salva.',
        'by' => 'Scritto da :provider',
        'error' => 'L’IA non ha risposto. Riprova più tardi.',
    ],
    'help' => [
        'open' => 'Chiedi all’assistente',
        'title' => 'Assistente',
        'hello' => 'Ciao! Conosco le guide di Atrium e posso consultare le tue sezioni. Cosa vuoi sapere?',
        'placeholder' => 'Chiedi del pannello o dei tuoi contenuti…',
        'send' => 'Invia',
        'thinking' => 'Sto pensando…',
        'note' => 'Leggo soltanto e non modifico nulla. Le risposte possono essere sbagliate.',
        'clear' => 'Nuova conversazione',
        'close' => 'Chiudi',
        'error' => 'Non sono riuscito a rispondere. Riprova più tardi.',
    ],
    'errors' => [
        'no_ai' => 'Questo campo non ha la generazione con IA.',
        'no_image' => 'Carica prima l’immagine.',
        'no_source' => 'Compila prima il nome o il testo: non c’è nulla da cui partire.',
        'not_translatable' => 'Questo campo non ha traduzioni.',
        'not_configured' => 'L’IA non è configurata: nessun provider ha una chiave.',
        'busy' => 'Tutti i provider di IA sono occupati o hanno superato il limite gratuito. Riprova tra un minuto.',
        'failed' => 'Il provider di IA non ha risposto. Riprova più tardi.',
    ],
];
