<?php

use App\Models\Forms\FormSubmission;

describe('Form Summary', function () {
    beforeEach(function () {
        $this->user = $this->actingAsProUser();
        $this->workspace = $this->createUserWorkspace($this->user);
        $this->form = $this->createForm($this->user, $this->workspace);
    });

    it('returns empty summary when no submissions exist', function () {
        $this->getJson(route('open.workspaces.form.summary', [$this->workspace, $this->form]))
            ->assertSuccessful()
            ->assertJson([
                'total_submissions' => 0,
                'processed_submissions' => 0,
                'is_limited' => false,
                'fields' => [],
            ]);
    });

    it('returns summary with correct total submissions count', function () {
        $nameProperty = collect($this->form->properties)->firstWhere('name', 'Name');

        // Create 5 completed submissions
        $this->form->submissions()->createMany([
            ['data' => [$nameProperty['id'] => 'John'], 'status' => FormSubmission::STATUS_COMPLETED],
            ['data' => [$nameProperty['id'] => 'Jane'], 'status' => FormSubmission::STATUS_COMPLETED],
            ['data' => [$nameProperty['id'] => 'Bob'], 'status' => FormSubmission::STATUS_COMPLETED],
            ['data' => [$nameProperty['id'] => 'Alice'], 'status' => FormSubmission::STATUS_COMPLETED],
            ['data' => [$nameProperty['id'] => 'Charlie'], 'status' => FormSubmission::STATUS_COMPLETED],
        ]);

        $this->getJson(route('open.workspaces.form.summary', [$this->workspace, $this->form]))
            ->assertSuccessful()
            ->assertJsonPath('total_submissions', 5)
            ->assertJsonPath('processed_submissions', 5)
            ->assertJsonPath('is_limited', false);
    });

    it('filters by status correctly', function () {
        $nameProperty = collect($this->form->properties)->firstWhere('name', 'Name');

        // Create mixed submissions
        $this->form->submissions()->createMany([
            ['data' => [$nameProperty['id'] => 'Complete 1'], 'status' => FormSubmission::STATUS_COMPLETED],
            ['data' => [$nameProperty['id'] => 'Complete 2'], 'status' => FormSubmission::STATUS_COMPLETED],
            ['data' => [$nameProperty['id'] => 'Partial 1'], 'status' => FormSubmission::STATUS_PARTIAL],
        ]);

        // Default (completed only)
        $this->getJson(route('open.workspaces.form.summary', [$this->workspace, $this->form]))
            ->assertSuccessful()
            ->assertJsonPath('total_submissions', 2);

        // Partial only
        $this->getJson(route('open.workspaces.form.summary', [$this->workspace, $this->form]) . '?status=partial')
            ->assertSuccessful()
            ->assertJsonPath('total_submissions', 1);

        // All submissions
        $this->getJson(route('open.workspaces.form.summary', [$this->workspace, $this->form]) . '?status=all')
            ->assertSuccessful()
            ->assertJsonPath('total_submissions', 3);
    });

    it('filters by date range correctly', function () {
        $nameProperty = collect($this->form->properties)->firstWhere('name', 'Name');

        // Create submissions on different dates
        $oldSubmission = $this->form->submissions()->create([
            'data' => [$nameProperty['id'] => 'Old'],
            'status' => FormSubmission::STATUS_COMPLETED,
        ]);
        $oldSubmission->created_at = now()->subDays(10);
        $oldSubmission->save();

        $recentSubmission = $this->form->submissions()->create([
            'data' => [$nameProperty['id'] => 'Recent'],
            'status' => FormSubmission::STATUS_COMPLETED,
        ]);
        $recentSubmission->created_at = now()->subDays(2);
        $recentSubmission->save();

        $this->form->submissions()->create([
            'data' => [$nameProperty['id'] => 'Today'],
            'status' => FormSubmission::STATUS_COMPLETED,
        ]);

        // Filter last 5 days
        $dateFrom = now()->subDays(5)->format('Y-m-d');
        $dateTo = now()->format('Y-m-d');

        $this->getJson(route('open.workspaces.form.summary', [$this->workspace, $this->form]) . "?date_from={$dateFrom}&date_to={$dateTo}")
            ->assertSuccessful()
            ->assertJsonPath('total_submissions', 2);
    });

    it('returns correct summary types for different field types', function () {
        $this->form->submissions()->create([
            'data' => $this->generateFormSubmissionData($this->form, [], true),
            'status' => FormSubmission::STATUS_COMPLETED,
        ]);

        $response = $this->getJson(route('open.workspaces.form.summary', [$this->workspace, $this->form]))
            ->assertSuccessful();

        $fields = collect($response->json('fields'));

        // Check text field has text_list summary type
        $textField = $fields->firstWhere('type', 'text');
        expect($textField['summary_type'])->toBe('text_list');

        // Check number field has numeric_stats summary type
        $numberField = $fields->firstWhere('type', 'number');
        expect($numberField['summary_type'])->toBe('numeric_stats');

        // Check rating field has rating summary type
        $ratingField = $fields->firstWhere('type', 'rating');
        expect($ratingField['summary_type'])->toBe('rating');

        // Check select field has distribution summary type
        $selectField = $fields->firstWhere('type', 'select');
        expect($selectField['summary_type'])->toBe('distribution');

        // Check checkbox field has boolean summary type
        $checkboxField = $fields->firstWhere('type', 'checkbox');
        expect($checkboxField['summary_type'])->toBe('boolean');

        // Check date field has date_summary summary type
        $dateField = $fields->firstWhere('type', 'date');
        expect($dateField['summary_type'])->toBe('date_summary');
    });

    it('calculates numeric stats correctly', function () {
        $numberProperty = collect($this->form->properties)->firstWhere('name', 'Number');

        // Create submissions with number values
        $this->form->submissions()->createMany([
            ['data' => [$numberProperty['id'] => 10], 'status' => FormSubmission::STATUS_COMPLETED],
            ['data' => [$numberProperty['id'] => 20], 'status' => FormSubmission::STATUS_COMPLETED],
            ['data' => [$numberProperty['id'] => 30], 'status' => FormSubmission::STATUS_COMPLETED],
            ['data' => [$numberProperty['id'] => 40], 'status' => FormSubmission::STATUS_COMPLETED],
            ['data' => [$numberProperty['id'] => 50], 'status' => FormSubmission::STATUS_COMPLETED],
        ]);

        $response = $this->getJson(route('open.workspaces.form.summary', [$this->workspace, $this->form]))
            ->assertSuccessful();

        $fields = collect($response->json('fields'));
        $numberField = $fields->firstWhere('type', 'number');

        expect($numberField['data']['average'])->toBe(30)
            ->and($numberField['data']['min'])->toBe(10)
            ->and($numberField['data']['max'])->toBe(50)
            ->and($numberField['data']['count'])->toBe(5);
    });

    it('calculates distribution correctly for select fields', function () {
        $selectProperty = collect($this->form->properties)->firstWhere('name', 'Select');

        // Create submissions with select values
        $this->form->submissions()->createMany([
            ['data' => [$selectProperty['id'] => 'First'], 'status' => FormSubmission::STATUS_COMPLETED],
            ['data' => [$selectProperty['id'] => 'First'], 'status' => FormSubmission::STATUS_COMPLETED],
            ['data' => [$selectProperty['id'] => 'First'], 'status' => FormSubmission::STATUS_COMPLETED],
            ['data' => [$selectProperty['id'] => 'Second'], 'status' => FormSubmission::STATUS_COMPLETED],
            ['data' => [$selectProperty['id'] => 'Second'], 'status' => FormSubmission::STATUS_COMPLETED],
        ]);

        $response = $this->getJson(route('open.workspaces.form.summary', [$this->workspace, $this->form]))
            ->assertSuccessful();

        $fields = collect($response->json('fields'));
        $selectField = $fields->firstWhere('type', 'select');
        $distribution = collect($selectField['data']['distribution']);

        $firstOption = $distribution->firstWhere('value', 'First');
        $secondOption = $distribution->firstWhere('value', 'Second');

        expect($firstOption['count'])->toBe(3)
            ->and($firstOption['percentage'])->toBe(60)
            ->and($secondOption['count'])->toBe(2)
            ->and($secondOption['percentage'])->toBe(40);
    });

    it('calculates boolean summary correctly', function () {
        $checkboxProperty = collect($this->form->properties)->firstWhere('name', 'Checkbox');

        // Create submissions with checkbox values
        $this->form->submissions()->createMany([
            ['data' => [$checkboxProperty['id'] => true], 'status' => FormSubmission::STATUS_COMPLETED],
            ['data' => [$checkboxProperty['id'] => true], 'status' => FormSubmission::STATUS_COMPLETED],
            ['data' => [$checkboxProperty['id'] => true], 'status' => FormSubmission::STATUS_COMPLETED],
            ['data' => [$checkboxProperty['id'] => false], 'status' => FormSubmission::STATUS_COMPLETED],
        ]);

        $response = $this->getJson(route('open.workspaces.form.summary', [$this->workspace, $this->form]))
            ->assertSuccessful();

        $fields = collect($response->json('fields'));
        $checkboxField = $fields->firstWhere('type', 'checkbox');
        $distribution = collect($checkboxField['data']['distribution']);

        $yesOption = $distribution->firstWhere('value', 'Yes');
        $noOption = $distribution->firstWhere('value', 'No');

        expect($yesOption['count'])->toBe(3)
            ->and($yesOption['percentage'])->toBe(75)
            ->and($noOption['count'])->toBe(1)
            ->and($noOption['percentage'])->toBe(25);
    });

    it('calculates rating summary with distribution', function () {
        $ratingProperty = collect($this->form->properties)->firstWhere('name', 'Rating');

        // Create submissions with rating values
        $this->form->submissions()->createMany([
            ['data' => [$ratingProperty['id'] => 5], 'status' => FormSubmission::STATUS_COMPLETED],
            ['data' => [$ratingProperty['id'] => 5], 'status' => FormSubmission::STATUS_COMPLETED],
            ['data' => [$ratingProperty['id'] => 4], 'status' => FormSubmission::STATUS_COMPLETED],
            ['data' => [$ratingProperty['id'] => 3], 'status' => FormSubmission::STATUS_COMPLETED],
            ['data' => [$ratingProperty['id'] => 1], 'status' => FormSubmission::STATUS_COMPLETED],
        ]);

        $response = $this->getJson(route('open.workspaces.form.summary', [$this->workspace, $this->form]))
            ->assertSuccessful();

        $fields = collect($response->json('fields'));
        $ratingField = $fields->firstWhere('type', 'rating');

        expect($ratingField['data']['average'])->toBe(3.6)
            ->and($ratingField['data']['min'])->toBe(1)
            ->and($ratingField['data']['max'])->toBe(5)
            ->and($ratingField['data']['distribution'][5])->toBe(2)
            ->and($ratingField['data']['distribution'][4])->toBe(1)
            ->and($ratingField['data']['distribution'][3])->toBe(1)
            ->and($ratingField['data']['distribution'][1])->toBe(1);
    });

    it('tracks answered count per field', function () {
        $nameProperty = collect($this->form->properties)->firstWhere('name', 'Name');
        $numberProperty = collect($this->form->properties)->firstWhere('name', 'Number');

        // Create submissions where some fields are not answered
        $this->form->submissions()->createMany([
            ['data' => [$nameProperty['id'] => 'John', $numberProperty['id'] => 10], 'status' => FormSubmission::STATUS_COMPLETED],
            ['data' => [$nameProperty['id'] => 'Jane'], 'status' => FormSubmission::STATUS_COMPLETED], // No number
            ['data' => [$numberProperty['id'] => 20], 'status' => FormSubmission::STATUS_COMPLETED], // No name
        ]);

        $response = $this->getJson(route('open.workspaces.form.summary', [$this->workspace, $this->form]))
            ->assertSuccessful();

        $fields = collect($response->json('fields'));

        $nameField = $fields->firstWhere('name', 'Name');
        $numberField = $fields->firstWhere('type', 'number');

        expect($nameField['answered_count'])->toBe(2)
            ->and($nameField['total_submissions'])->toBe(3)
            ->and($numberField['answered_count'])->toBe(2)
            ->and($numberField['total_submissions'])->toBe(3);
    });

    it('requires authentication', function () {
        $this->actingAsGuest();

        $this->getJson(route('open.workspaces.form.summary', [$this->workspace, $this->form]))
            ->assertStatus(401);
    });

    it('requires authorization to view form', function () {
        $otherUser = $this->createUser();
        $otherWorkspace = $this->createUserWorkspace($otherUser);
        $otherForm = $this->createForm($otherUser, $otherWorkspace);

        // Try to access other user's form
        $this->getJson(route('open.workspaces.form.summary', [$otherWorkspace, $otherForm]))
            ->assertStatus(403);
    });
});

describe('Form Summary Field Values (Load More)', function () {
    beforeEach(function () {
        $this->user = $this->actingAsProUser();
        $this->workspace = $this->createUserWorkspace($this->user);
        $this->form = $this->createForm($this->user, $this->workspace);
    });

    it('returns paginated text values', function () {
        // Get the field ID for Name
        $nameProperty = collect($this->form->properties)->firstWhere('name', 'Name');

        // Create 15 submissions with names
        for ($i = 1; $i <= 15; $i++) {
            $this->form->submissions()->create([
                'data' => [$nameProperty['id'] => "User {$i}"],
                'status' => FormSubmission::STATUS_COMPLETED,
            ]);
        }

        // Initial load (first 10)
        $response = $this->getJson(route('open.workspaces.form.summary.field-values', [
            $this->workspace,
            $this->form,
            $nameProperty['id'],
        ]))
            ->assertSuccessful();

        expect($response->json('displayed_count'))->toBe(10)
            ->and($response->json('total_count'))->toBe(15)
            ->and($response->json('has_more'))->toBeTrue()
            ->and($response->json('next_offset'))->toBe(10);

        // Load more (remaining 5)
        $response = $this->getJson(route('open.workspaces.form.summary.field-values', [
            $this->workspace,
            $this->form,
            $nameProperty['id'],
        ]) . '?offset=10')
            ->assertSuccessful();

        expect($response->json('displayed_count'))->toBe(5)
            ->and($response->json('total_count'))->toBe(15)
            ->and($response->json('has_more'))->toBeFalse();
    });

    it('returns 404 for non-existent field', function () {
        $this->getJson(route('open.workspaces.form.summary.field-values', [
            $this->workspace,
            $this->form,
            'non-existent-field-id',
        ]))
            ->assertStatus(404)
            ->assertJson(['error' => 'Field not found']);
    });

    it('respects date and status filters', function () {
        $nameProperty = collect($this->form->properties)->firstWhere('name', 'Name');

        // Create submissions
        $oldSubmission = $this->form->submissions()->create([
            'data' => [$nameProperty['id'] => 'Old User'],
            'status' => FormSubmission::STATUS_COMPLETED,
        ]);
        $oldSubmission->created_at = now()->subDays(10);
        $oldSubmission->save();

        $this->form->submissions()->create([
            'data' => [$nameProperty['id'] => 'Recent User'],
            'status' => FormSubmission::STATUS_COMPLETED,
        ]);

        // Filter last 5 days
        $dateFrom = now()->subDays(5)->format('Y-m-d');
        $dateTo = now()->format('Y-m-d');

        $response = $this->getJson(route('open.workspaces.form.summary.field-values', [
            $this->workspace,
            $this->form,
            $nameProperty['id'],
        ]) . "?date_from={$dateFrom}&date_to={$dateTo}")
            ->assertSuccessful();

        expect($response->json('total_count'))->toBe(1);
    });
});

describe('Form Summary Request Validation', function () {
    beforeEach(function () {
        $this->user = $this->actingAsProUser();
        $this->workspace = $this->createUserWorkspace($this->user);
        $this->form = $this->createForm($this->user, $this->workspace);
    });

    it('validates date_from must be before date_to', function () {
        $this->getJson(route('open.workspaces.form.summary', [$this->workspace, $this->form]) . '?date_from=2024-01-10&date_to=2024-01-05')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_from']);
    });

    it('validates status must be valid option', function () {
        $this->getJson(route('open.workspaces.form.summary', [$this->workspace, $this->form]) . '?status=invalid')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    });

    it('validates offset must be non-negative integer', function () {
        $nameProperty = collect($this->form->properties)->firstWhere('name', 'Name');

        $this->getJson(route('open.workspaces.form.summary.field-values', [
            $this->workspace,
            $this->form,
            $nameProperty['id'],
        ]) . '?offset=-1')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['offset']);
    });
});
