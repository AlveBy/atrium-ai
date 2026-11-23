<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'settings' => [
        'module' => 'AI',
        'description' => '面板角落里 AI 助手的样子。',
        'playful' => '活泼的助手',
        'playful_hint' => '眉毛、微笑和小小的装扮会显示助手正在做什么。关闭时：安静的拱门。无论哪种样子，它都不会自己弹出或开口说话。',
    ],
    'ui' => [
        'generate' => '生成',
        'translate' => '翻译',
        'improve' => '优化',
        'improve_selection' => '优化所选内容',
        'dialog_title' => 'AI 建议：:field',
        'translate_title' => '翻译：:field',
        'improve_title' => '优化后的文本：:field',
        'menu' => 'AI 助手',
        'working' => '正在撰写…',
        'accept' => '使用此文本',
        'cancel' => '取消',
        'retry' => '重试',
        'check' => '保存前请检查：AI 会出错。点击“保存”之前不会保存任何内容。',
        'by' => '由 :provider 撰写',
        'error' => 'AI 没有响应，请稍后再试。',
        'demo_notice' => '此演示是公开的：请勿输入个人信息、他人的信息或任何私密内容。',
    ],
    'help' => [
        'open' => '询问助手',
        'title' => '助手',
        'hello' => '你好！我了解 Atrium 的文档，也可以查看你的各个栏目。想了解什么？',
        'placeholder' => '询问后台或你的内容…',
        'send' => '发送',
        'thinking' => '思考中…',
        'note' => '我只读取，不做任何修改。回答可能有误。',
        'clear' => '新对话',
        'close' => '关闭',
        'error' => '无法回答，请稍后再试。',
        'mood' => [
            'thinking' => '助手正在思考',
            'happy' => '助手已回答',
            'sorry' => '助手无法回答',
            'search' => '助手正在查找记录',
            'read' => '助手正在阅读指南',
            'write' => 'AI 正在撰写文本',
        ],
    ],
    'errors' => [
        'no_ai' => '此字段没有 AI 生成。',
        'no_image' => '请先上传图片。',
        'no_source' => '请先填写名称或正文：没有可依据的内容。',
        'too_long' => '文本太长，无法一次优化。请分段优化。',
        'not_translatable' => '此字段没有翻译。',
        'not_configured' => 'AI 未配置：没有任何提供商设置了密钥。',
        'busy' => '所有 AI 提供商都忙或已超出免费额度，请一分钟后再试。',
        'failed' => 'AI 提供商没有响应，请稍后再试。',
        'demo_limit' => '演示的每日 AI 使用额度已用完，请明天再试。',
    ],
];
