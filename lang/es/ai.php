<?php

declare(strict_types=1);

return [
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
    ],
    'errors' => [
        'no_ai' => 'Este campo no tiene generación con IA.',
        'no_image' => 'Primero suba la imagen.',
        'no_source' => 'Primero rellene el nombre o el texto: no hay de dónde partir.',
        'not_translatable' => 'Este campo no tiene traducciones.',
        'not_configured' => 'La IA no está configurada: ningún proveedor tiene clave.',
        'busy' => 'Todos los proveedores de IA están ocupados o superaron su límite gratuito. Inténtelo en un minuto.',
        'failed' => 'El proveedor de IA no respondió. Inténtelo más tarde.',
    ],
];
