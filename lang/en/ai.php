<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

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
        'demo_notice' => 'This demo is public: do not type personal data, other people\'s data or anything private.',
    ],
    'help' => [
        'open' => 'Ask the helper',
        'title' => 'Helper',
        'hello' => 'Hi! I know the Atrium guides and can look through your sections. What would you like to know?',
        'placeholder' => 'Ask about the panel or your content…',
        'send' => 'Send',
        'thinking' => 'Thinking…',
        'note' => 'I only read and change nothing. Answers can be wrong.',
        'clear' => 'New conversation',
        'close' => 'Close',
        'error' => 'I could not answer. Try again later.',
    ],
    'errors' => [
        'no_ai' => 'This field has no AI generation.',
        'no_image' => 'Upload the image first.',
        'no_source' => 'Fill in the name or the text first: there is nothing to work from.',
        'not_translatable' => 'This field has no translations.',
        'not_configured' => 'AI is not set up: no provider has a key.',
        'busy' => 'All AI providers are busy or over their free limit. Try again in a minute.',
        'failed' => 'The AI provider did not answer. Try again later.',
        'demo_limit' => 'The demo\'s daily AI limit is used up. Try again tomorrow.',
    ],
];
