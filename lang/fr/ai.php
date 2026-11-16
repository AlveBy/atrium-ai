<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'ui' => [
        'generate' => 'Générer',
        'translate' => 'Traduire',
        'dialog_title' => 'Suggestion de l’IA : :field',
        'translate_title' => 'Traduction : :field',
        'working' => 'Rédaction…',
        'accept' => 'Utiliser ce texte',
        'cancel' => 'Annuler',
        'retry' => 'Réessayer',
        'check' => 'Relisez avant d’enregistrer : l’IA se trompe. Rien n’est enregistré avant que vous cliquiez sur Enregistrer.',
        'by' => 'Rédigé par :provider',
        'error' => 'L’IA n’a pas répondu. Réessayez plus tard.',
    ],
    'errors' => [
        'no_ai' => 'Ce champ n’a pas de génération par IA.',
        'no_image' => 'Téléversez d’abord l’image.',
        'no_source' => 'Remplissez d’abord le nom ou le texte : il n’y a rien sur quoi s’appuyer.',
        'not_translatable' => 'Ce champ n’a pas de traductions.',
        'not_configured' => 'L’IA n’est pas configurée : aucun fournisseur n’a de clé.',
        'busy' => 'Tous les fournisseurs d’IA sont occupés ou ont dépassé leur quota gratuit. Réessayez dans une minute.',
        'failed' => 'Le fournisseur d’IA n’a pas répondu. Réessayez plus tard.',
    ],
];
