<?php

use App\Service\Forms\FormCleaner;
use Illuminate\Http\Request;

uses(\Tests\TestCase::class);

describe('FormCleaner custom code policy', function () {
    beforeEach(function () {
        // Sensible defaults for tests
        config()->set('app.self_hosted', false);
        config()->set('opnform.custom_code.enable_self_hosted', false);
    });

    it('suppresses custom_code and removes nf-code on SaaS without custom domain', function () {
        $user = $this->actingAsUser();
        $workspace = $this->createUserWorkspace($user);

        $form = $this->createForm($user, $workspace, [
            'custom_domain' => null,
            'custom_code' => '<script>steal()</script>',
            'custom_css' => 'body{color:red}',
            'properties' => [
                [
                    'id' => 't1',
                    'name' => 'Text',
                    'type' => 'nf-text',
                    'content' => '<script>x</script><p>safe</p>'
                ],
                [
                    'id' => 'c1',
                    'name' => 'Code',
                    'type' => 'nf-code',
                    'content' => '<script>bad()</script>'
                ],
                [
                    'id' => 'email1',
                    'name' => 'Email',
                    'type' => 'email',
                ],
            ],
        ]);

        $request = Request::create('/', 'GET');
        $cleaner = (new FormCleaner())->processForm($request, $form);
        $data = $cleaner->getData();

        // custom_code suppressed
        expect($data['custom_code'])->toBeNull();
        // custom_css preserved
        expect($data['custom_css'])->toBe('body{color:red}');

        // nf-code removed entirely, nf-text sanitized, email remains
        $types = array_map(fn ($p) => $p['type'], $data['properties']);
        expect($types)->not->toContain('nf-code');

        $textBlock = collect($data['properties'])->firstWhere('id', 't1');
        expect($textBlock['content'])->not->toContain('<script>')
            ->and($textBlock['content'])->toContain('safe');

        // Policy-based removals are silent (no cleanings recorded)
        $messages = $cleaner->getPerformedCleanings();
        expect($messages)->toBeArray()->and($messages)->toBeEmpty();
    });

    it('keeps custom_code and nf-code when form has a custom domain', function () {
        $user = $this->actingAsUser();
        $workspace = $this->createUserWorkspace($user);

        $form = $this->createForm($user, $workspace, [
            'custom_domain' => 'example.com',
            'custom_code' => '<script>ok()</script>',
            'properties' => [
                [ 'id' => 'c1', 'name' => 'Code', 'type' => 'nf-code', 'content' => '<script>a()</script>' ],
            ],
        ]);

        $request = Request::create('/', 'GET');
        $cleaner = (new FormCleaner())->processForm($request, $form);
        $data = $cleaner->getData();

        expect($data['custom_code'])->not->toBeNull();
        $types = array_map(fn ($p) => $p['type'], $data['properties']);
        expect($types)->toContain('nf-code');
    });

    it('keeps custom_code and nf-code when self-hosted flag enabled', function () {
        config()->set('app.self_hosted', true);
        config()->set('opnform.custom_code.enable_self_hosted', true);

        $user = $this->actingAsUser();
        $workspace = $this->createUserWorkspace($user);

        $form = $this->createForm($user, $workspace, [
            'custom_domain' => null,
            'custom_code' => '<script>ok()</script>',
            'properties' => [
                [ 'id' => 'c1', 'name' => 'Code', 'type' => 'nf-code', 'content' => '<script>a()</script>' ],
            ],
        ]);

        $request = Request::create('/', 'GET');
        $cleaner = (new FormCleaner())->processForm($request, $form);
        $data = $cleaner->getData();

        expect($data['custom_code'])->not->toBeNull();
        $types = array_map(fn ($p) => $p['type'], $data['properties']);
        expect($types)->toContain('nf-code');
    });
});
