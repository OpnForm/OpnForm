<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => ':attribute mora biti prihvaćen.',
    'active_url' => ':attribute nije validan URL.',
    'after' => ':attribute mora biti datum posle :date.',
    'after_or_equal' => ':attribute mora biti datum posle ili jednak :date.',
    'alpha' => ':attribute može da sadrži samo slova.',
    'alpha_dash' => ':attribute može da sadrži samo slova, brojeve, crtice i donje crte.',
    'alpha_num' => ':attribute može da sadrži samo slova i brojeve.',
    'array' => ':attribute mora biti niz.',
    'before' => ':attribute mora biti datum pre :date.',
    'before_or_equal' => ':attribute mora biti datum pre ili jednak :date.',
    'between' => [
        'numeric' => ':attribute mora biti između :min i :max.',
        'file' => ':attribute mora biti između :min i :max kilobajta.',
        'string' => ':attribute mora imati između :min i :max karaktera.',
        'array' => ':attribute mora imati između :min i :max stavki.',
    ],
    'boolean' => 'Polje :attribute mora biti tačno ili netačno.',
    'confirmed' => 'Potvrda polja :attribute se ne poklapa.',
    'date' => ':attribute nije važeći datum.',
    'date_equals' => ':attribute mora biti datum jednak :date.',
    'date_format' => ':attribute ne odgovara formatu :format.',
    'different' => ':attribute i :other moraju biti različiti.',
    'digits' => ':attribute mora imati :digits cifara.',
    'digits_between' => ':attribute mora imati između :min i :max cifara.',
    'dimensions' => ':attribute ima nevažeće dimenzije slike.',
    'distinct' => 'Polje :attribute ima duplu vrednost.',
    'email' => ':attribute mora biti važeća email adresa.',
    'ends_with' => ':attribute mora se završavati jednim od sledećih: :values.',
    'exists' => 'Izabrani :attribute je nevažeći.',
    'file' => ':attribute mora biti fajl.',
    'filled' => 'Polje :attribute mora imati vrednost.',
    'gt' => [
        'numeric' => ':attribute mora biti veći od :value.',
        'file' => ':attribute mora biti veći od :value kilobajta.',
        'string' => ':attribute mora imati više od :value karaktera.',
        'array' => ':attribute mora imati više od :value stavki.',
    ],
    'gte' => [
        'numeric' => ':attribute mora biti veći ili jednak :value.',
        'file' => ':attribute mora biti veći ili jednak :value kilobajta.',
        'string' => ':attribute mora imati najmanje :value karaktera.',
        'array' => ':attribute mora imati :value stavki ili više.',
    ],
    'image' => ':attribute mora biti slika.',
    'in' => 'Izabrani :attribute je nevažeći.',
    'in_array' => 'Polje :attribute ne postoji u :other.',
    'integer' => ':attribute mora biti ceo broj.',
    'ip' => ':attribute mora biti važeća IP adresa.',
    'ipv4' => ':attribute mora biti važeća IPv4 adresa.',
    'ipv6' => ':attribute mora biti važeća IPv6 adresa.',
    'json' => ':attribute mora biti važeći JSON string.',
    'lt' => [
        'numeric' => ':attribute mora biti manji od :value.',
        'file' => ':attribute mora biti manji od :value kilobajta.',
        'string' => ':attribute mora imati manje od :value karaktera.',
        'array' => ':attribute mora imati manje od :value stavki.',
    ],
    'lte' => [
        'numeric' => ':attribute mora biti manji ili jednak :value.',
        'file' => ':attribute mora biti manji ili jednak :value kilobajta.',
        'string' => ':attribute mora imati najviše :value karaktera.',
        'array' => ':attribute ne sme imati više od :value stavki.',
    ],
    'max' => [
        'numeric' => ':attribute ne sme biti veći od :max.',
        'file' => ':attribute ne sme biti veći od :max kilobajta.',
        'string' => ':attribute ne sme imati više od :max karaktera.',
        'array' => ':attribute ne sme imati više od :max stavki.',
    ],
    'mimes' => ':attribute mora biti fajl tipa: :values.',
    'mimetypes' => ':attribute mora biti fajl tipa: :values.',
    'min' => [
        'numeric' => ':attribute mora biti najmanje :min.',
        'file' => ':attribute mora biti najmanje :min kilobajta.',
        'string' => ':attribute mora imati najmanje :min karaktera.',
        'array' => ':attribute mora imati najmanje :min stavki.',
    ],
    'multiple_of' => ':attribute mora biti umnožak vrednosti :value',
    'not_in' => 'Izabrani :attribute je nevažeći.',
    'not_regex' => 'Format polja :attribute je nevažeći.',
    'numeric' => ':attribute mora biti broj.',
    'password' => 'Lozinka je netačna.',
    'present' => 'Polje :attribute mora biti prisutno.',
    'regex' => 'Format polja :attribute je nevažeći.',
    'required' => 'Polje :attribute je obavezno.',
    'required_if' => 'Polje :attribute je obavezno kada je :other :value.',
    'required_unless' => 'Polje :attribute je obavezno osim ako :other nije u :values.',
    'required_with' => 'Polje :attribute je obavezno kada je prisutno :values.',
    'required_with_all' => 'Polje :attribute je obavezno kada su prisutne :values.',
    'required_without' => 'Polje :attribute je obavezno kada :values nije prisutno.',
    'required_without_all' => 'Polje :attribute je obavezno kada nijedna od :values nije prisutna.',
    'same' => ':attribute i :other moraju da se poklapaju.',
    'size' => [
        'numeric' => ':attribute mora biti :size.',
        'file' => ':attribute mora biti :size kilobajta.',
        'string' => ':attribute mora imati :size karaktera.',
        'array' => ':attribute mora sadržati :size stavki.',
    ],
    'starts_with' => ':attribute mora počinjati jednim od sledećih: :values.',
    'string' => ':attribute mora biti ispravan tekst.',
    'timezone' => ':attribute mora biti važeća vremenska zona.',
    'unique' => ':attribute je već zauzet.',
    'uploaded' => ':attribute nije uspeo da se otpremi.',
    'url' => 'Format polja :attribute je nevažeći.',
    'uuid' => ':attribute mora biti važeći UUID.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'prilagođena-poruka',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    */

    'attributes' => [],

    'invalid_json' => 'Neispravan unos. Ispravite i pokušajte ponovo.',
    'invalid_captcha' => 'Nevažeća CAPTCHA. Dokažite da niste robot.',
    'complete_captcha' => 'Molimo završite captcha.',
    'yes' => 'Da',
    'no' => 'Ne',

    'from_date_required' => 'Datum od je obavezan',
    'to_date_required' => 'Datum do je obavezan',
    'from_date_before_or_equal' => 'Datum od mora biti pre ili jednak datumu do',
    'rating_min' => 'Mora biti izabrana ocena',
    'select_min' => 'Molimo izaberite najmanje :min opcija',
    'select_max' => 'Molimo izaberite najviše :max opcija',
];
