<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'settings' => [
        'module' => 'AI',
        'description' => 'パネルの隅にいる AI アシスタントの見た目。',
        'playful' => 'にぎやかなアシスタント',
        'playful_hint' => '眉や笑顔、小さな変身でアシスタントが何をしているかを表します。オフ：落ち着いたアーチ。どちらの場合も、勝手に現れたり話しかけたりはしません。',
    ],
    'ui' => [
        'generate' => '生成',
        'translate' => '翻訳',
        'improve' => '改善',
        'improve_selection' => '選択部分を改善',
        'dialog_title' => 'AI の提案: :field',
        'translate_title' => '翻訳: :field',
        'improve_title' => '改善したテキスト: :field',
        'menu' => 'AI アシスト',
        'working' => '作成中…',
        'accept' => 'このテキストを使う',
        'cancel' => 'キャンセル',
        'retry' => '再試行',
        'check' => '保存する前に確認してください。AI は間違えます。「保存」を押すまで何も保存されません。',
        'by' => ':provider が作成',
        'error' => 'AI が応答しませんでした。後でもう一度お試しください。',
        'demo_notice' => 'このデモは公開されています。個人情報、他人の情報、プライベートな内容は入力しないでください。',
    ],
    'help' => [
        'open' => 'アシスタントに質問',
        'title' => 'アシスタント',
        'hello' => 'こんにちは！Atrium のガイドを知っていて、各セクションの中身も確認できます。何を知りたいですか？',
        'placeholder' => '管理画面やコンテンツについて質問してください…',
        'send' => '送信',
        'thinking' => '考え中…',
        'note' => '読むだけで、何も変更しません。回答が間違っている場合があります。',
        'clear' => '新しい会話',
        'close' => '閉じる',
        'error' => '回答できませんでした。しばらくしてからお試しください。',
        'mood' => [
            'thinking' => 'アシスタントが考えています',
            'happy' => 'アシスタントが回答しました',
            'sorry' => 'アシスタントは回答できませんでした',
            'search' => 'アシスタントがレコードを探しています',
            'read' => 'アシスタントがガイドを読んでいます',
            'write' => 'AI が文章を書いています',
        ],
    ],
    'errors' => [
        'no_ai' => 'このフィールドには AI 生成がありません。',
        'no_image' => '先に画像をアップロードしてください。',
        'no_source' => '先に名前または本文を入力してください。元になる情報がありません。',
        'too_long' => 'テキストが長すぎて一度に改善できません。部分ごとに改善してください。',
        'not_translatable' => 'このフィールドには翻訳がありません。',
        'not_configured' => 'AI が設定されていません。キーのあるプロバイダーがありません。',
        'busy' => 'すべての AI プロバイダーが混雑しているか、無料枠を超えました。1 分後にもう一度お試しください。',
        'failed' => 'AI プロバイダーが応答しませんでした。後でもう一度お試しください。',
        'demo_limit' => 'デモの1日のAI利用上限に達しました。明日もう一度お試しください。',
    ],
];
