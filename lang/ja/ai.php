<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'ui' => [
        'generate' => '生成',
        'translate' => '翻訳',
        'dialog_title' => 'AI の提案: :field',
        'translate_title' => '翻訳: :field',
        'working' => '作成中…',
        'accept' => 'このテキストを使う',
        'cancel' => 'キャンセル',
        'retry' => '再試行',
        'check' => '保存する前に確認してください。AI は間違えます。「保存」を押すまで何も保存されません。',
        'by' => ':provider が作成',
        'error' => 'AI が応答しませんでした。後でもう一度お試しください。',
    ],
    'errors' => [
        'no_ai' => 'このフィールドには AI 生成がありません。',
        'no_image' => '先に画像をアップロードしてください。',
        'no_source' => '先に名前または本文を入力してください。元になる情報がありません。',
        'not_translatable' => 'このフィールドには翻訳がありません。',
        'not_configured' => 'AI が設定されていません。キーのあるプロバイダーがありません。',
        'busy' => 'すべての AI プロバイダーが混雑しているか、無料枠を超えました。1 分後にもう一度お試しください。',
        'failed' => 'AI プロバイダーが応答しませんでした。後でもう一度お試しください。',
    ],
];
