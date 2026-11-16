<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'ui' => [
        'generate' => 'Gerar',
        'translate' => 'Traduzir',
        'dialog_title' => 'Sugestão da IA: :field',
        'translate_title' => 'Tradução: :field',
        'working' => 'Escrevendo…',
        'accept' => 'Usar este texto',
        'cancel' => 'Cancelar',
        'retry' => 'Tentar de novo',
        'check' => 'Leia antes de salvar: a IA erra. Nada é salvo até você clicar em Salvar.',
        'by' => 'Escrito por :provider',
        'error' => 'A IA não respondeu. Tente mais tarde.',
    ],
    'errors' => [
        'no_ai' => 'Este campo não tem geração por IA.',
        'no_image' => 'Envie a imagem primeiro.',
        'no_source' => 'Preencha primeiro o nome ou o texto: não há de onde partir.',
        'not_translatable' => 'Este campo não tem traduções.',
        'not_configured' => 'A IA não está configurada: nenhum provedor tem chave.',
        'busy' => 'Todos os provedores de IA estão ocupados ou passaram do limite gratuito. Tente em um minuto.',
        'failed' => 'O provedor de IA não respondeu. Tente mais tarde.',
    ],
];
