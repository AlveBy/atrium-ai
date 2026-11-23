<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'settings' => [
        'module' => 'IA',
        'description' => 'Cómo se ve el asistente de IA en la esquina del panel.',
        'playful' => 'Asistente divertido',
        'playful_hint' => 'Cejas, una sonrisa y pequeños disfraces muestran lo que hace el asistente. Desactivado: el arco tranquilo. En ningún caso aparece ni habla por sí solo.',
    ],
    'ui' => [
        'generate' => 'Generar',
        'translate' => 'Traducir',
        'dialog_title' => 'Sugerencia de la IA: :field',
        'translate_title' => 'Traducción: :field',
        'working' => 'Escribiendo…',
        'accept' => 'Usar este texto',
        'cancel' => 'Cancelar',
        'retry' => 'Reintentar',
        'check' => 'Léalo antes de guardar: la IA se equivoca. No se guarda nada hasta que pulse Guardar.',
        'by' => 'Escrito por :provider',
        'error' => 'La IA no respondió. Inténtelo más tarde.',
        'demo_notice' => 'Esta demo es pública: no escriba datos personales, datos de otras personas ni nada privado.',
    ],
    'help' => [
        'open' => 'Preguntar al asistente',
        'title' => 'Asistente',
        'hello' => '¡Hola! Conozco las guías de Atrium y puedo consultar tus secciones. ¿Qué quieres saber?',
        'placeholder' => 'Pregunta sobre el panel o tus contenidos…',
        'send' => 'Enviar',
        'thinking' => 'Pensando…',
        'note' => 'Solo leo y no cambio nada. Las respuestas pueden ser incorrectas.',
        'clear' => 'Nueva conversación',
        'close' => 'Cerrar',
        'error' => 'No pude responder. Inténtalo más tarde.',
        'mood' => [
            'thinking' => 'El asistente está pensando',
            'happy' => 'El asistente ha respondido',
            'sorry' => 'El asistente no pudo responder',
            'search' => 'El asistente está buscando en los registros',
            'read' => 'El asistente está leyendo las guías',
            'write' => 'La IA está escribiendo un texto',
        ],
    ],
    'errors' => [
        'no_ai' => 'Este campo no tiene generación con IA.',
        'no_image' => 'Primero suba la imagen.',
        'no_source' => 'Primero rellene el nombre o el texto: no hay de dónde partir.',
        'not_translatable' => 'Este campo no tiene traducciones.',
        'not_configured' => 'La IA no está configurada: ningún proveedor tiene clave.',
        'busy' => 'Todos los proveedores de IA están ocupados o superaron su límite gratuito. Inténtelo en un minuto.',
        'failed' => 'El proveedor de IA no respondió. Inténtelo más tarde.',
        'demo_limit' => 'Se agotó el límite diario de IA de la demo. Inténtelo de nuevo mañana.',
    ],
];
