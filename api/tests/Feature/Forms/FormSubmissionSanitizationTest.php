<?php

use Illuminate\Support\Str;

describe('Form submission sanitization', function () {
    it('preserves plain text-like fields and cleans rich_text', function () {
        $user = $this->actingAsUser();
        $workspace = $this->createUserWorkspace($user);
        $form = $this->createForm($user, $workspace);

        // Identify a few default fields by type
        $nameField = collect($form->properties)->firstWhere('name', 'Name'); // text
        $emailField = collect($form->properties)->firstWhere('type', 'email');
        $numberField = collect($form->properties)->firstWhere('type', 'number');

        // Add a rich_text field
        $richTextField = [
            'id' => 'rt_' . Str::uuid()->toString(),
            'name' => 'Rich Text',
            'type' => 'rich_text',
        ];
        $form->properties = array_merge($form->properties, [$richTextField]);
        $form->save();

        $xss = "<img src=x onerror=alert('xss')>Hello";
        $rtXss = "<p onclick=alert(1)><script>alert(1)</script><a href=javascript:alert(1)>bad</a><strong>ok</strong></p>";

        $payload = [
            $nameField['id'] => $xss,
            $emailField['id'] => 'attacker@example.com',
            $numberField['id'] => 42,
            $richTextField['id'] => $rtXss,
        ];

        $this->postJson(route('forms.answer', $form->slug), $payload)
            ->assertSuccessful()
            ->assertJson([
                'type' => 'success',
            ]);

        $submission = $form->submissions()->latest()->first();

        // Plain text should preserve raw string (not stripped); UI escapes it
        expect($submission->data[$nameField['id']])->toBe($xss);

        // Email untouched (already validated) and number intact
        expect($submission->data[$emailField['id']])->toBe('attacker@example.com');
        expect($submission->data[$numberField['id']])->toBe(42);

        // Rich text should be cleaned: no script/onclick/javascript: but keep <strong> and wrapping <p>
        $cleaned = $submission->data[$richTextField['id']];
        expect($cleaned)->not->toContain('<script');
        expect($cleaned)->not->toContain('onclick');
        expect($cleaned)->not->toContain('javascript:');
        expect($cleaned)->toContain('<strong>ok</strong>');
    });
});
