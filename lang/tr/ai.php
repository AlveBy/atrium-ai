<?php

declare(strict_types=1);

return [
    'ui' => [
        'generate' => 'Oluştur',
        'translate' => 'Çevir',
        'dialog_title' => 'Yapay zekâ önerisi: :field',
        'translate_title' => 'Çeviri: :field',
        'working' => 'Yazılıyor…',
        'accept' => 'Bu metni kullan',
        'cancel' => 'İptal',
        'retry' => 'Tekrar dene',
        'check' => 'Kaydetmeden önce okuyun: yapay zekâ hata yapar. Kaydet’e basana kadar hiçbir şey kaydedilmez.',
        'by' => ':provider yazdı',
        'error' => 'Yapay zekâ yanıt vermedi. Daha sonra tekrar deneyin.',
    ],
    'errors' => [
        'no_ai' => 'Bu alanda yapay zekâ ile oluşturma yok.',
        'no_image' => 'Önce görseli yükleyin.',
        'no_source' => 'Önce adı veya metni doldurun: yazılacak bir kaynak yok.',
        'not_translatable' => 'Bu alanın çevirileri yok.',
        'not_configured' => 'Yapay zekâ ayarlanmamış: hiçbir sağlayıcının anahtarı yok.',
        'busy' => 'Tüm yapay zekâ sağlayıcıları meşgul veya ücretsiz sınırını aştı. Bir dakika sonra tekrar deneyin.',
        'failed' => 'Yapay zekâ sağlayıcısı yanıt vermedi. Daha sonra tekrar deneyin.',
    ],
];
