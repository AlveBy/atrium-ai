<?php

declare(strict_types=1);

return [
    'ui' => [
        'generate' => 'Generate',
        'translate' => 'Translate',
        'dialog_title' => 'AI suggestion: :field',
        'translate_title' => 'Translation: :field',
        'working' => 'Writing…',
        'accept' => 'Use this text',
        'cancel' => 'Cancel',
        'retry' => 'Try again',
        'check' => 'Read it before saving: AI makes mistakes. Nothing is saved until you press Save.',
        'by' => 'Written by :provider',
        'error' => 'The AI did not answer. Try again later.',
    ],
    'errors' => [
        'no_ai' => 'This field has no AI generation.',
        'no_image' => 'Upload the image first.',
        'no_source' => 'Fill in the name or the text first: there is nothing to work from.',
        'not_translatable' => 'This field has no translations.',
        'not_configured' => 'AI is not set up: no provider has a key.',
        'busy' => 'All AI providers are busy or over their free limit. Try again in a minute.',
        'failed' => 'The AI provider did not answer. Try again later.',
    ],
];
