<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'settings' => [
        'module' => 'Yapay zekâ',
        'description' => 'Paneldeki köşede duran yapay zekâ asistanının görünümü.',
        'playful' => 'Neşeli asistan',
        'playful_hint' => 'Kaşlar, bir gülümseme ve küçük kılıklar asistanın ne yaptığını gösterir. Kapalıyken: sakin kemer. Her iki durumda da kendiliğinden açılmaz ve konuşmaz.',
    ],
    'ui' => [
        'generate' => 'Oluştur',
        'translate' => 'Çevir',
        'improve' => 'İyileştir',
        'improve_selection' => 'Seçileni iyileştir',
        'dialog_title' => 'Yapay zekâ önerisi: :field',
        'translate_title' => 'Çeviri: :field',
        'improve_title' => 'İyileştirilmiş metin: :field',
        'menu' => 'Yapay zekâ yardımı',
        'working' => 'Yazılıyor…',
        'accept' => 'Bu metni kullan',
        'cancel' => 'İptal',
        'retry' => 'Tekrar dene',
        'check' => 'Kaydetmeden önce okuyun: yapay zekâ hata yapar. Kaydet’e basana kadar hiçbir şey kaydedilmez.',
        'by' => ':provider yazdı',
        'error' => 'Yapay zekâ yanıt vermedi. Daha sonra tekrar deneyin.',
        'demo_notice' => 'Bu demo herkese açıktır: kişisel verileri, başkalarına ait verileri veya özel hiçbir şeyi yazmayın.',
    ],
    'help' => [
        'open' => 'Asistana sor',
        'title' => 'Asistan',
        'hello' => 'Merhaba! Atrium kılavuzlarını biliyorum ve bölümlerinize bakabilirim. Ne öğrenmek istersiniz?',
        'placeholder' => 'Panel veya içerikleriniz hakkında sorun…',
        'send' => 'Gönder',
        'thinking' => 'Düşünüyorum…',
        'note' => 'Yalnızca okurum, hiçbir şeyi değiştirmem. Yanıtlar hatalı olabilir.',
        'clear' => 'Yeni sohbet',
        'close' => 'Kapat',
        'error' => 'Yanıt veremedim. Daha sonra tekrar deneyin.',
        'mood' => [
            'thinking' => 'Asistan düşünüyor',
            'happy' => 'Asistan yanıtladı',
            'sorry' => 'Asistan yanıt veremedi',
            'search' => 'Asistan kayıtlara bakıyor',
            'read' => 'Asistan kılavuzları okuyor',
            'write' => 'Yapay zekâ bir metin yazıyor',
        ],
    ],
    'errors' => [
        'no_ai' => 'Bu alanda yapay zekâ ile oluşturma yok.',
        'no_image' => 'Önce görseli yükleyin.',
        'no_source' => 'Önce adı veya metni doldurun: yazılacak bir kaynak yok.',
        'too_long' => 'Metin tek seferde iyileştirilemeyecek kadar uzun. Parça parça iyileştirin.',
        'not_translatable' => 'Bu alanın çevirileri yok.',
        'not_configured' => 'Yapay zekâ ayarlanmamış: hiçbir sağlayıcının anahtarı yok.',
        'busy' => 'Tüm yapay zekâ sağlayıcıları meşgul veya ücretsiz sınırını aştı. Bir dakika sonra tekrar deneyin.',
        'failed' => 'Yapay zekâ sağlayıcısı yanıt vermedi. Daha sonra tekrar deneyin.',
        'demo_limit' => 'Demonun günlük yapay zekâ sınırı doldu. Yarın tekrar deneyin.',
    ],
];
