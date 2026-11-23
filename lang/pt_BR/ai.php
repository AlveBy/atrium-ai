<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'settings' => [
        'module' => 'IA',
        'description' => 'Como o assistente de IA aparece no canto do painel.',
        'playful' => 'Assistente divertido',
        'playful_hint' => 'Sobrancelhas, um sorriso e pequenas fantasias mostram o que o assistente está fazendo. Desligado: o arco tranquilo. Em nenhum caso ele aparece ou fala sozinho.',
    ],
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
        'demo_notice' => 'Esta demonstração é pública: não digite dados pessoais, dados de outras pessoas nem nada privado.',
    ],
    'help' => [
        'open' => 'Perguntar ao assistente',
        'title' => 'Assistente',
        'hello' => 'Olá! Conheço os guias do Atrium e posso consultar suas seções. O que você quer saber?',
        'placeholder' => 'Pergunte sobre o painel ou seus conteúdos…',
        'send' => 'Enviar',
        'thinking' => 'Pensando…',
        'note' => 'Eu só leio e não altero nada. As respostas podem estar erradas.',
        'clear' => 'Nova conversa',
        'close' => 'Fechar',
        'error' => 'Não consegui responder. Tente novamente mais tarde.',
        'mood' => [
            'thinking' => 'O assistente está pensando',
            'happy' => 'O assistente respondeu',
            'sorry' => 'O assistente não conseguiu responder',
            'search' => 'O assistente está procurando nos registros',
            'read' => 'O assistente está lendo os guias',
            'write' => 'A IA está escrevendo um texto',
        ],
    ],
    'errors' => [
        'no_ai' => 'Este campo não tem geração por IA.',
        'no_image' => 'Envie a imagem primeiro.',
        'no_source' => 'Preencha primeiro o nome ou o texto: não há de onde partir.',
        'not_translatable' => 'Este campo não tem traduções.',
        'not_configured' => 'A IA não está configurada: nenhum provedor tem chave.',
        'busy' => 'Todos os provedores de IA estão ocupados ou passaram do limite gratuito. Tente em um minuto.',
        'failed' => 'O provedor de IA não respondeu. Tente mais tarde.',
        'demo_limit' => 'O limite diário de IA da demonstração foi atingido. Tente novamente amanhã.',
    ],
];
