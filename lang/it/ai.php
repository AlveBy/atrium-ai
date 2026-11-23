<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'settings' => [
        'module' => 'IA',
        'description' => 'Come appare l’assistente IA nell’angolo del pannello.',
        'playful' => 'Assistente vivace',
        'playful_hint' => 'Sopracciglia, un sorriso e piccoli travestimenti mostrano cosa sta facendo l’assistente. Spento: l’arco tranquillo. In nessun caso compare o parla da solo.',
    ],
    'ui' => [
        'generate' => 'Genera',
        'translate' => 'Traduci',
        'improve' => 'Migliora',
        'improve_selection' => 'Migliora la selezione',
        'dialog_title' => 'Proposta dell’IA: :field',
        'translate_title' => 'Traduzione: :field',
        'improve_title' => 'Testo migliorato: :field',
        'menu' => 'Aiuto dell\'IA',
        'working' => 'Scrittura…',
        'accept' => 'Usa questo testo',
        'cancel' => 'Annulla',
        'retry' => 'Riprova',
        'check' => 'Rileggi prima di salvare: l’IA sbaglia. Non viene salvato nulla finché non premi Salva.',
        'by' => 'Scritto da :provider',
        'error' => 'L’IA non ha risposto. Riprova più tardi.',
        'demo_notice' => 'Questa demo è pubblica: non inserire dati personali, dati altrui o qualcosa di privato.',
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
        'mood' => [
            'thinking' => 'L’assistente sta pensando',
            'happy' => 'L’assistente ha risposto',
            'sorry' => 'L’assistente non è riuscito a rispondere',
            'search' => 'L’assistente sta cercando tra i record',
            'read' => 'L’assistente sta leggendo le guide',
            'write' => 'L’IA sta scrivendo un testo',
        ],
    ],
    'errors' => [
        'no_ai' => 'Questo campo non ha la generazione con IA.',
        'no_image' => 'Carica prima l’immagine.',
        'no_source' => 'Compila prima il nome o il testo: non c’è nulla da cui partire.',
        'too_long' => 'Il testo è troppo lungo per essere migliorato in una volta. Miglioralo a parti.',
        'not_translatable' => 'Questo campo non ha traduzioni.',
        'not_configured' => 'L’IA non è configurata: nessun provider ha una chiave.',
        'busy' => 'Tutti i provider di IA sono occupati o hanno superato il limite gratuito. Riprova tra un minuto.',
        'failed' => 'Il provider di IA non ha risposto. Riprova più tardi.',
        'demo_limit' => 'Il limite giornaliero di IA della demo è esaurito. Riprova domani.',
    ],
];
