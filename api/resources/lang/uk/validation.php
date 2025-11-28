<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines (Ukrainian)
    |--------------------------------------------------------------------------
    */

    'accepted' => ':attribute має бути прийнятий.',
    'active_url' => ':attribute не є дійсною URL-адресою.',
    'after' => ':attribute має бути датою після :date.',
    'after_or_equal' => ':attribute має бути датою після або рівною :date.',
    'alpha' => ':attribute може містити лише літери.',
    'alpha_dash' => ':attribute може містити лише літери, цифри, тире та підкреслення.',
    'alpha_num' => ':attribute може містити лише літери та цифри.',
    'array' => ':attribute має бути масивом.',
    'before' => ':attribute має бути датою до :date.',
    'before_or_equal' => ':attribute має бути датою до або рівною :date.',
    'between' => [
        'numeric' => ':attribute має бути між :min та :max.',
        'file' => ':attribute має бути від :min до :max кілобайт.',
        'string' => ':attribute має містити від :min до :max символів.',
        'array' => ':attribute має містити від :min до :max елементів.',
    ],
    'boolean' => 'Поле :attribute має бути істинним або хибним.',
    'confirmed' => 'Підтвердження для :attribute не збігається.',
    'date' => ':attribute не є дійсною датою.',
    'date_equals' => ':attribute має бути датою, рівною :date.',
    'date_format' => ':attribute не відповідає формату :format.',
    'different' => ':attribute та :other мають бути різними.',
    'digits' => ':attribute має містити :digits цифр.',
    'digits_between' => ':attribute має містити від :min до :max цифр.',
    'dimensions' => ':attribute має недійсні розміри зображення.',
    'distinct' => 'Поле :attribute містить повторюване значення.',
    'email' => ':attribute має бути дійсною електронною адресою.',
    'ends_with' => ':attribute має закінчуватися одним із таких значень: :values.',
    'exists' => 'Обраний :attribute недійсний.',
    'file' => ':attribute має бути файлом.',
    'filled' => 'Поле :attribute має містити значення.',
    'gt' => [
        'numeric' => ':attribute має бути більшим за :value.',
        'file' => ':attribute має бути більшим за :value кілобайт.',
        'string' => ':attribute має містити більше ніж :value символів.',
        'array' => ':attribute має містити більше ніж :value елементів.',
    ],
    'gte' => [
        'numeric' => ':attribute має бути не меншим за :value.',
        'file' => ':attribute має бути не меншим за :value кілобайт.',
        'string' => ':attribute має містити не менше :value символів.',
        'array' => ':attribute має містити :value елементів або більше.',
    ],
    'image' => ':attribute має бути зображенням.',
    'in' => 'Обраний :attribute недійсний.',
    'in_array' => 'Поле :attribute не існує у :other.',
    'integer' => ':attribute має бути цілим числом.',
    'ip' => ':attribute має бути дійсною IP-адресою.',
    'ipv4' => ':attribute має бути дійсною IPv4-адресою.',
    'ipv6' => ':attribute має бути дійсною IPv6-адресою.',
    'json' => ':attribute має бути дійсним JSON-рядком.',
    'lt' => [
        'numeric' => ':attribute має бути меншим за :value.',
        'file' => ':attribute має бути меншим за :value кілобайт.',
        'string' => ':attribute має містити менше ніж :value символів.',
        'array' => ':attribute має містити менше ніж :value елементів.',
    ],
    'lte' => [
        'numeric' => ':attribute має бути не більшим за :value.',
        'file' => ':attribute має бути не більшим за :value кілобайт.',
        'string' => ':attribute має містити не більше :value символів.',
        'array' => ':attribute не повинно містити більше ніж :value елементів.',
    ],
    'max' => [
        'numeric' => ':attribute не може бути більшим за :max.',
        'file' => ':attribute не може бути більшим за :max кілобайт.',
        'string' => ':attribute не може містити більше ніж :max символів.',
        'array' => ':attribute не може містити більше ніж :max елементів.',
    ],
    'mimes' => ':attribute має бути файлом типу: :values.',
    'mimetypes' => ':attribute має бути файлом типу: :values.',
    'min' => [
        'numeric' => ':attribute має бути не меншим за :min.',
        'file' => ':attribute має бути не меншим за :min кілобайт.',
        'string' => ':attribute має містити не менше :min символів.',
        'array' => ':attribute має містити щонайменше :min елементів.',
    ],
    'multiple_of' => ':attribute має бути кратним :value.',
    'not_in' => 'Обраний :attribute недійсний.',
    'not_regex' => 'Неприпустимий формат поля :attribute.',
    'numeric' => ':attribute має бути числом.',
    'password' => 'Невірний пароль.',
    'present' => 'Поле :attribute має бути присутнім.',
    'regex' => 'Неприпустимий формат поля :attribute.',
    'required' => 'Поле :attribute є обов\'язковим.',
    'required_if' => 'Поле :attribute є обов\'язковим, коли :other дорівнює :value.',
    'required_unless' => 'Поле :attribute є обов\'язковим, якщо :other не знаходиться у :values.',
    'required_with' => 'Поле :attribute є обов\'язковим, коли присутнє :values.',
    'required_with_all' => 'Поле :attribute є обов\'язковим, коли присутні :values.',
    'required_without' => 'Поле :attribute є обов\'язковим, коли :values відсутнє.',
    'required_without_all' => 'Поле :attribute є обов\'язковим, коли жодне з :values не присутнє.',
    'same' => ':attribute і :other повинні збігатися.',
    'size' => [
        'numeric' => ':attribute має бути :size.',
        'file' => ':attribute має бути :size кілобайт.',
        'string' => ':attribute має містити :size символів.',
        'array' => ':attribute має містити :size елементів.',
    ],
    'starts_with' => ':attribute має починатися з одного з наступних значень: :values.',
    'string' => ':attribute має бути рядком.',
    'timezone' => ':attribute має бути дійсним часовим поясом.',
    'unique' => ':attribute вже зайнятий.',
    'uploaded' => ':attribute не вдалося завантажити.',
    'url' => 'Неприпустимий формат поля :attribute.',
    'uuid' => ':attribute має бути дійсним UUID.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'спеціальне-повідомлення',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    */

    'attributes' => [],

    'invalid_json' => 'Некоректне введення. Виправте і спробуйте ще раз.',
    'invalid_captcha' => 'Недійсна CAPTCHA. Підтвердіть, що ви не робот.',
    'complete_captcha' => 'Будь ласка, пройдіть капчу.',
    'yes' => 'Так',
    'no' => 'Ні',

    'from_date_required' => 'Потрібна дата початку',
    'to_date_required' => 'Потрібна дата завершення',
    'from_date_before_or_equal' => 'Дата початку має бути раніше або дорівнювати даті завершення',
    'rating_min' => 'Потрібно вибрати оцінку',
    'select_min' => 'Виберіть щонайменше :min варіантів',
    'select_max' => 'Виберіть не більше ніж :max варіантів',
];
