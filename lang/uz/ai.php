<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'settings' => [
        'module' => 'SI',
        'description' => 'Panel burchagidagi SI yordamchisining ko‘rinishi.',
        'playful' => 'Quvnoq yordamchi',
        'playful_hint' => 'Qoshlar, tabassum va kichik o‘zgarishlar yordamchi nima qilayotganini ko‘rsatadi. O‘chirilganda — sokin arka. Har ikki holatda ham u o‘zi chiqib kelmaydi va gapirmaydi.',
    ],
    'ui' => [
        'generate' => 'Yaratish',
        'translate' => 'Tarjima qilish',
        'dialog_title' => 'SI taklifi: :field',
        'translate_title' => 'Tarjima: :field',
        'working' => 'Yozilmoqda…',
        'accept' => 'Shu matnni qo‘yish',
        'cancel' => 'Bekor qilish',
        'retry' => 'Yana urinish',
        'check' => 'Saqlashdan oldin o‘qing: SI xato qiladi. «Saqlash»ni bosmaguningizcha hech narsa saqlanmaydi.',
        'by' => 'Yozgan: :provider',
        'error' => 'SI javob bermadi. Keyinroq urinib ko‘ring.',
        'demo_notice' => 'Bu ommaviy demo: shaxsiy maʼlumotlarni, boshqalarning maʼlumotlarini va hech qanday maxfiy narsani kiritmang.',
    ],
    'help' => [
        'open' => 'Yordamchidan so‘rash',
        'title' => 'Yordamchi',
        'hello' => 'Salom! Men Atrium hujjatlarini bilaman va bo‘limlaringizni ko‘rib chiqa olaman. Nima haqida aytib beray?',
        'placeholder' => 'Boshqaruv paneli yoki yozuvlaringiz haqida so‘rang…',
        'send' => 'Yuborish',
        'thinking' => 'O‘ylayapman…',
        'note' => 'Men faqat o‘qiyman, hech narsani o‘zgartirmayman. Javoblar xato bo‘lishi mumkin.',
        'clear' => 'Yangi suhbat',
        'close' => 'Yopish',
        'error' => 'Javob bera olmadim. Keyinroq urinib ko‘ring.',
        'mood' => [
            'thinking' => 'Yordamchi o‘ylayapti',
            'happy' => 'Yordamchi javob berdi',
            'sorry' => 'Yordamchi javob bera olmadi',
            'search' => 'Yordamchi yozuvlardan qidiryapti',
            'read' => 'Yordamchi qo‘llanmani o‘qiyapti',
            'write' => 'SI matn yozyapti',
        ],
    ],
    'errors' => [
        'no_ai' => 'Bu maydonda SI bilan yaratish yo‘q.',
        'no_image' => 'Avval rasmni yuklang.',
        'no_source' => 'Avval nom yoki matnni to‘ldiring: yozish uchun manba yo‘q.',
        'not_translatable' => 'Bu maydonning tarjimalari yo‘q.',
        'not_configured' => 'SI sozlanmagan: hech bir provayderda kalit yo‘q.',
        'busy' => 'Barcha SI provayderlari band yoki bepul limiti tugagan. Bir daqiqadan so‘ng urinib ko‘ring.',
        'failed' => 'SI provayderi javob bermadi. Keyinroq urinib ko‘ring.',
        'demo_limit' => 'Demodagi sun’iy intellektning kunlik limiti tugadi. Ertaga qayta urinib ko‘ring.',
    ],
];
