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

describe('FormCleaner tier-based cleaning', function () {
    beforeEach(function () {
        config()->set('app.self_hosted', false);
        config()->set('opnform.custom_code.enable_self_hosted', false);
    });

    it('cleans pro features from free tier workspace', function () {
        $user = $this->actingAsUser(); // Free tier
        $workspace = $this->createUserWorkspace($user);

        $form = $this->createForm($user, $workspace, [
            'no_branding' => true,
            'redirect_url' => 'https://example.com/thanks',
        ]);

        $request = Request::create('/', 'GET');
        $cleaner = (new FormCleaner())->processForm($request, $form);
        $cleaner->performCleaning($workspace);
        $data = $cleaner->getData();

        // Pro features should be cleaned for free tier
        expect($data['no_branding'])->toBeFalse();
        expect($data['redirect_url'])->toBeNull();
    });

    it('keeps pro features for pro tier workspace', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);

        $form = $this->createForm($user, $workspace, [
            'no_branding' => true,
            'redirect_url' => 'https://example.com/thanks',
        ]);

        $request = Request::create('/', 'GET');
        $cleaner = (new FormCleaner())->processForm($request, $form);
        $cleaner->performCleaning($workspace);
        $data = $cleaner->getData();

        // Pro features should remain for pro tier
        expect($data['no_branding'])->toBeTrue();
        expect($data['redirect_url'])->toBe('https://example.com/thanks');
    });

    it('cleans business features from pro tier workspace', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);

        $form = $this->createForm($user, $workspace, [
            'enable_partial_submissions' => true,
            'editable_submissions' => true,
            'no_branding' => true,
        ]);

        $request = Request::create('/', 'GET');
        $cleaner = (new FormCleaner())->processForm($request, $form);
        $cleaner->performCleaning($workspace);
        $data = $cleaner->getData();

        // Business features should be cleaned for pro tier
        expect($data['enable_partial_submissions'])->toBeFalse();
        expect($data['editable_submissions'])->toBeFalse();

        // Pro features should remain
        expect($data['no_branding'])->toBeTrue();
    });

    it('keeps business features for business tier workspace', function () {
        $user = $this->actingAsBusinessUser();
        $workspace = $this->createUserWorkspace($user);

        $form = $this->createForm($user, $workspace, [
            'enable_partial_submissions' => true,
            'editable_submissions' => true,
            'no_branding' => true,
        ]);

        $request = Request::create('/', 'GET');
        $cleaner = (new FormCleaner())->processForm($request, $form);
        $cleaner->performCleaning($workspace);
        $data = $cleaner->getData();

        // Business features should remain
        expect($data['enable_partial_submissions'])->toBeTrue();
        expect($data['editable_submissions'])->toBeTrue();
        expect($data['no_branding'])->toBeTrue();
    });

    it('cleans enterprise features from business tier workspace', function () {
        $user = $this->actingAsBusinessUser();
        $workspace = $this->createUserWorkspace($user);

        $form = $this->createForm($user, $workspace, [
            'enable_ip_tracking' => true,
            'enable_partial_submissions' => true,
            'no_branding' => true,
        ]);

        $request = Request::create('/', 'GET');
        $cleaner = (new FormCleaner())->processForm($request, $form);
        $cleaner->performCleaning($workspace);
        $data = $cleaner->getData();

        // Enterprise features should be cleaned for business tier
        expect($data['enable_ip_tracking'])->toBeFalse();

        // Business and pro features should remain
        expect($data['enable_partial_submissions'])->toBeTrue();
        expect($data['no_branding'])->toBeTrue();
    });

    it('keeps all features for enterprise tier workspace', function () {
        $user = $this->actingAsEnterpriseUser();
        $workspace = $this->createUserWorkspace($user);

        $form = $this->createForm($user, $workspace, [
            'enable_ip_tracking' => true,
            'enable_partial_submissions' => true,
            'editable_submissions' => true,
            'no_branding' => true,
            'redirect_url' => 'https://example.com',
        ]);

        $request = Request::create('/', 'GET');
        $cleaner = (new FormCleaner())->processForm($request, $form);
        $cleaner->performCleaning($workspace);
        $data = $cleaner->getData();

        // All features should remain for enterprise tier
        expect($data['enable_ip_tracking'])->toBeTrue();
        expect($data['enable_partial_submissions'])->toBeTrue();
        expect($data['editable_submissions'])->toBeTrue();
        expect($data['no_branding'])->toBeTrue();
        expect($data['redirect_url'])->toBe('https://example.com');
    });

    it('cleans secret_input field for free tier', function () {
        $user = $this->actingAsUser(); // Free tier
        $workspace = $this->createUserWorkspace($user);

        $form = $this->createForm($user, $workspace, [
            'properties' => [
                [
                    'id' => 'field1',
                    'name' => 'Secret',
                    'type' => 'text',
                    'secret_input' => true,
                ],
            ],
        ]);

        $request = Request::create('/', 'GET');
        $cleaner = (new FormCleaner())->processForm($request, $form);
        $cleaner->performCleaning($workspace);
        $data = $cleaner->getData();

        $secretField = collect($data['properties'])->firstWhere('id', 'field1');
        expect($secretField['secret_input'])->toBeFalse();
    });

    it('keeps secret_input field for pro tier', function () {
        $user = $this->actingAsProUser();
        $workspace = $this->createUserWorkspace($user);

        $form = $this->createForm($user, $workspace, [
            'properties' => [
                [
                    'id' => 'field1',
                    'name' => 'Secret',
                    'type' => 'text',
                    'secret_input' => true,
                ],
            ],
        ]);

        $request = Request::create('/', 'GET');
        $cleaner = (new FormCleaner())->processForm($request, $form);
        $cleaner->performCleaning($workspace);
        $data = $cleaner->getData();

        $secretField = collect($data['properties'])->firstWhere('id', 'field1');
        expect($secretField['secret_input'])->toBeTrue();
    });

    it('records cleanings when features are removed', function () {
        $user = $this->actingAsUser(); // Free tier
        $workspace = $this->createUserWorkspace($user);

        $form = $this->createForm($user, $workspace, [
            'no_branding' => true,
            'redirect_url' => 'https://example.com',
            'enable_partial_submissions' => true,
            'enable_ip_tracking' => true,
        ]);

        $request = Request::create('/', 'GET');
        $cleaner = (new FormCleaner())->processForm($request, $form);
        $cleaner->performCleaning($workspace);

        expect($cleaner->hasCleaned())->toBeTrue();

        $cleanings = $cleaner->getPerformedCleanings();
        expect($cleanings)->toHaveKey('form');
        expect($cleanings['form']->toArray())->toContain('OpenForm branding is not hidden.');
        expect($cleanings['form']->toArray())->toContain('Redirect Url was disabled');
        expect($cleanings['form']->toArray())->toContain('Partial submissions were disabled');
        expect($cleanings['form']->toArray())->toContain('IP tracking was disabled');
    });

    it('simulation does not modify data but records cleanings', function () {
        $user = $this->actingAsUser(); // Free tier
        $workspace = $this->createUserWorkspace($user);

        $form = $this->createForm($user, $workspace, [
            'no_branding' => true,
            'redirect_url' => 'https://example.com',
        ]);

        $request = Request::create('/', 'GET');
        $cleaner = (new FormCleaner())->processForm($request, $form);
        $cleaner->simulateCleaning($workspace);
        $data = $cleaner->getData();

        // Data should NOT be modified in simulation
        expect($data['no_branding'])->toBeTrue();
        expect($data['redirect_url'])->toBe('https://example.com');

        // But cleanings should be recorded
        expect($cleaner->hasCleaned())->toBeTrue();
    });
});
