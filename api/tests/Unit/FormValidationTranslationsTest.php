<?php

use App\Http\Requests\AnswerFormRequest;
use App\Models\Forms\Form;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;

function formValidationTranslator(string $locale): Translator
{
    $translator = new Translator(new FileLoader(new Filesystem(), __DIR__ . '/../../resources/lang'), $locale);
    $translator->setFallback('en');

    return $translator;
}

it('keeps validation keys, placeholders and encoding intact in every supported form language', function (string $locale) {
    $english = Arr::dot(require __DIR__ . '/../../resources/lang/en/validation.php');
    $translated = Arr::dot(require __DIR__ . "/../../resources/lang/$locale/validation.php");

    // Password catalogs use different Laravel generations (scalar vs. nested keys).
    foreach ($english as $key => $message) {
        if ($key === 'password' || !is_string($message) || str_starts_with($key, 'custom.') || str_starts_with($key, 'attributes.')) {
            continue;
        }
        expect($translated)->toHaveKey($key);
        preg_match_all('/:[a-z_]+/', $message, $expected);
        preg_match_all('/:[a-z_]+/', $translated[$key], $actual);
        expect(array_diff($expected[0], $actual[0]))->toBe([], "$locale: $key placeholders");
    }
    foreach ($translated as $message) {
        if (is_string($message)) {
            expect($message)->not->toContain("\u{FFFD}");
        }
    }
})->with(Form::LANGUAGES);

it('renders date range, rating and selection failures through the real request messages', function (string $locale) {
    $previousContainer = Container::getInstance();
    $container = new Container();
    $translator = formValidationTranslator($locale);
    $container->instance('translator', $translator);
    Container::setInstance($container);

    try {
        // messages() only needs form properties, not the constructor's workspace lookup.
        $request = (new ReflectionClass(AnswerFormRequest::class))->newInstanceWithoutConstructor();
        $request->form = new Form(['properties' => [
            ['id' => 'period', 'name' => 'Period', 'type' => 'date', 'date_range' => true],
            ['id' => 'rating', 'name' => 'Rating', 'type' => 'rating'],
            ['id' => 'choices', 'name' => 'Choices', 'type' => 'multi_select', 'min_selection' => 2, 'max_selection' => 3],
        ]]);
        $factory = new Factory($translator);
        $cases = [
            [['period' => [null, '2026-10-05']], ['period.0' => 'required_with:period.1'], 'period.0', 'from_date_required'],
            [['period' => ['2026-10-05', null]], ['period.1' => 'required_with:period.0'], 'period.1', 'to_date_required'],
            [['period' => ['2026-10-06', '2026-10-05']], ['period.0' => 'before_or_equal:period.1'], 'period.0', 'from_date_before_or_equal'],
            [['rating' => 0], ['rating' => 'numeric|min:1'], 'rating', 'rating_min'],
            [['choices' => ['A']], ['choices' => 'array|min:2'], 'choices', 'select_min'],
            [['choices' => ['A', 'B', 'C', 'D']], ['choices' => 'array|max:3'], 'choices', 'select_max'],
        ];
        foreach ($cases as [$data, $rules, $field, $key]) {
            $validator = $factory->make($data, $rules, $request->messages());
            expect($validator->fails())->toBeTrue();
            $message = $validator->errors()->first($field);
            expect($message)->toBe($translator->get("validation.$key", ['min' => 2, 'max' => 3], $locale, false));
            expect($message)->not->toContain(':min', ':max', 'validation.');
            if ($locale !== 'en') {
                expect($message)->not->toBe(formValidationTranslator('en')->get("validation.$key", ['min' => 2, 'max' => 3]));
            }
        }
    } finally {
        Container::setInstance($previousContainer);
    }
})->with(Form::LANGUAGES);

it('keeps the Arabic field name and allowed file types in file validation errors', function (string $rule) {
    $factory = new Factory(formValidationTranslator('ar'));
    $validator = $factory->make(['upload' => 'invalid'], ['upload' => "$rule:pdf"], [], ['upload' => 'المرفق']);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('upload'))->toContain('المرفق', 'pdf')->not->toContain(':attribute', ':values');
})->with(['mimes', 'mimetypes']);
