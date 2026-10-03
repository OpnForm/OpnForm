<?php

use App\Models\Forms\Form;
use App\Service\Forms\FormSubmissionFormatter;

function formatterForField(string $type, mixed $value): FormSubmissionFormatter
{
    $fieldId = 'field-id';
    $form = new Form([
        'properties' => [[
            'id' => $fieldId,
            'name' => 'Field',
            'type' => $type,
        ]],
        'removed_properties' => [],
    ]);

    return (new FormSubmissionFormatter($form, [$fieldId => $value]))
        ->createLinks()
        ->showHiddenFields();
}

it('formats safe submission links consistently', function (string $type, mixed $value, string $expected) {
    $formatter = formatterForField($type, $value);

    expect($formatter->getCleanKeyValue())->toBe([
        'Field (field-id)' => $expected,
    ]);

    expect($formatter->getFieldsWithValue()[0])
        ->toMatchArray([
            'value' => $expected,
            'value_is_html' => true,
        ]);
})->with([
    'https url' => [
        'url',
        'https://example.com/path?a=1&b=2',
        '<a href="https://example.com/path?a=1&amp;b=2">https://example.com/path?a=1&amp;b=2</a>',
    ],
    'http url' => [
        'url',
        'http://example.com/path',
        '<a href="http://example.com/path">http://example.com/path</a>',
    ],
    'url without scheme' => [
        'url',
        'example.com/path',
        '<a href="https://example.com/path">example.com/path</a>',
    ],
    'email' => [
        'email',
        'person@example.com',
        '<a href="mailto:person@example.com">person@example.com</a>',
    ],
]);

it('renders unsafe or invalid links as escaped text', function (string $type, mixed $value, string $expected) {
    $formatter = formatterForField($type, $value);

    expect($formatter->getCleanKeyValue())->toBe([
        'Field (field-id)' => $expected,
    ]);

    expect($formatter->getFieldsWithValue()[0])
        ->toMatchArray([
            'value' => $expected,
            'value_is_html' => true,
        ]);
})->with([
    'javascript url' => [
        'url',
        'javascript://example.com/%0Aalert(1)',
        'javascript://example.com/%0Aalert(1)',
    ],
    'data url' => [
        'url',
        'data://text/html,<script>alert(1)</script>',
        'data://text/html,&lt;script&gt;alert(1)&lt;/script&gt;',
    ],
    'file url' => [
        'url',
        'file:///etc/passwd',
        'file:///etc/passwd',
    ],
    'ftp url' => [
        'url',
        'ftp://example.com/file',
        'ftp://example.com/file',
    ],
    'attribute injection' => [
        'url',
        'https://example.com/" onclick="alert(1)',
        'https://example.com/&quot; onclick=&quot;alert(1)',
    ],
    'url with explicit user info' => [
        'url',
        'https://trusted.example@evil.example/path',
        'https://trusted.example@evil.example/path',
    ],
    'url without scheme containing user info' => [
        'url',
        'trusted.example@evil.example/path',
        'trusted.example@evil.example/path',
    ],
    'invalid email' => [
        'email',
        'person@example.com" onclick="alert(1)',
        'person@example.com&quot; onclick=&quot;alert(1)',
    ],
    'non-scalar url' => [
        'url',
        ['https://example.com', '<script>alert(1)</script>'],
        'https://example.com, &lt;script&gt;alert(1)&lt;/script&gt;',
    ],
]);
