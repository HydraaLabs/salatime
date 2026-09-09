<?php

return [
    'timezone' => 'Africa/Casablanca',
    'method' => 'MOROCCO',

    'locales' => [
        'fr' => [
            'world_prefix' => 'fr/horaires-priere',
            'prefix' => 'fr/horaires-priere/maroc',
            'language_tag' => 'fr',
            'og_locale' => 'fr_FR',
            'country_name' => 'Maroc',
        ],
        'ar' => [
            'world_prefix' => 'ar/prayer-times',
            'prefix' => 'ar/prayer-times/morocco',
            'language_tag' => 'ar',
            'og_locale' => 'ar_MA',
            'country_name' => 'المغرب',
        ],
        'en' => [
            'world_prefix' => 'en/prayer-times',
            'prefix' => 'en/prayer-times/morocco',
            'language_tag' => 'en',
            'og_locale' => 'en_US',
            'country_name' => 'Morocco',
        ],
        'es' => [
            'world_prefix' => 'es/horarios-oracion',
            'prefix' => 'es/horarios-oracion/marruecos',
            'language_tag' => 'es',
            'og_locale' => 'es_ES',
            'country_name' => 'Marruecos',
        ],
    ],

    // A curated list keeps every indexable page useful and reviewable. Coordinates
    // are city-centre reference points; calculations use Africa/Casablanca.
    'cities' => [
        'casablanca' => [
            'latitude' => 33.5731,
            'longitude' => -7.5898,
            'names' => ['fr' => 'Casablanca', 'ar' => 'الدار البيضاء', 'en' => 'Casablanca', 'es' => 'Casablanca'],
        ],
        'rabat' => [
            'latitude' => 34.0209,
            'longitude' => -6.8416,
            'names' => ['fr' => 'Rabat', 'ar' => 'الرباط', 'en' => 'Rabat', 'es' => 'Rabat'],
        ],
        'fes' => [
            'latitude' => 34.0181,
            'longitude' => -5.0078,
            'names' => ['fr' => 'Fès', 'ar' => 'فاس', 'en' => 'Fez', 'es' => 'Fez'],
        ],
        'marrakech' => [
            'latitude' => 31.6295,
            'longitude' => -7.9811,
            'names' => ['fr' => 'Marrakech', 'ar' => 'مراكش', 'en' => 'Marrakesh', 'es' => 'Marrakech'],
        ],
        'tanger' => [
            'latitude' => 35.7595,
            'longitude' => -5.8340,
            'names' => ['fr' => 'Tanger', 'ar' => 'طنجة', 'en' => 'Tangier', 'es' => 'Tánger'],
        ],
        'agadir' => [
            'latitude' => 30.4278,
            'longitude' => -9.5981,
            'names' => ['fr' => 'Agadir', 'ar' => 'أكادير', 'en' => 'Agadir', 'es' => 'Agadir'],
        ],
        'meknes' => [
            'latitude' => 33.8935,
            'longitude' => -5.5473,
            'names' => ['fr' => 'Meknès', 'ar' => 'مكناس', 'en' => 'Meknes', 'es' => 'Mequinez'],
        ],
        'oujda' => [
            'latitude' => 34.6814,
            'longitude' => -1.9086,
            'names' => ['fr' => 'Oujda', 'ar' => 'وجدة', 'en' => 'Oujda', 'es' => 'Uchda'],
        ],
        'kenitra' => [
            'latitude' => 34.2610,
            'longitude' => -6.5802,
            'names' => ['fr' => 'Kénitra', 'ar' => 'القنيطرة', 'en' => 'Kenitra', 'es' => 'Kenitra'],
        ],
        'tetouan' => [
            'latitude' => 35.5889,
            'longitude' => -5.3626,
            'names' => ['fr' => 'Tétouan', 'ar' => 'تطوان', 'en' => 'Tetouan', 'es' => 'Tetuán'],
        ],
        'safi' => [
            'latitude' => 32.2994,
            'longitude' => -9.2372,
            'names' => ['fr' => 'Safi', 'ar' => 'آسفي', 'en' => 'Safi', 'es' => 'Safi'],
        ],
        'el-jadida' => [
            'latitude' => 33.2316,
            'longitude' => -8.5007,
            'names' => ['fr' => 'El Jadida', 'ar' => 'الجديدة', 'en' => 'El Jadida', 'es' => 'El Yadida'],
        ],
        'beni-mellal' => [
            'latitude' => 32.3373,
            'longitude' => -6.3498,
            'names' => ['fr' => 'Béni Mellal', 'ar' => 'بني ملال', 'en' => 'Beni Mellal', 'es' => 'Beni Melal'],
        ],
        'nador' => [
            'latitude' => 35.1681,
            'longitude' => -2.9335,
            'names' => ['fr' => 'Nador', 'ar' => 'الناظور', 'en' => 'Nador', 'es' => 'Nador'],
        ],
        'settat' => [
            'latitude' => 33.0010,
            'longitude' => -7.6166,
            'names' => ['fr' => 'Settat', 'ar' => 'سطات', 'en' => 'Settat', 'es' => 'Settat'],
        ],
        'larache' => [
            'latitude' => 35.1932,
            'longitude' => -6.1557,
            'names' => ['fr' => 'Larache', 'ar' => 'العرائش', 'en' => 'Larache', 'es' => 'Larache'],
        ],
        'khouribga' => [
            'latitude' => 32.8811,
            'longitude' => -6.9063,
            'names' => ['fr' => 'Khouribga', 'ar' => 'خريبكة', 'en' => 'Khouribga', 'es' => 'Juribga'],
        ],
        'essaouira' => [
            'latitude' => 31.5085,
            'longitude' => -9.7595,
            'names' => ['fr' => 'Essaouira', 'ar' => 'الصويرة', 'en' => 'Essaouira', 'es' => 'Esauira'],
        ],
        'ouarzazate' => [
            'latitude' => 30.9335,
            'longitude' => -6.9370,
            'names' => ['fr' => 'Ouarzazate', 'ar' => 'ورزازات', 'en' => 'Ouarzazate', 'es' => 'Uarzazat'],
        ],
        'al-hoceima' => [
            'latitude' => 35.2517,
            'longitude' => -3.9372,
            'names' => ['fr' => 'Al Hoceïma', 'ar' => 'الحسيمة', 'en' => 'Al Hoceima', 'es' => 'Alhucemas'],
        ],
    ],
];
