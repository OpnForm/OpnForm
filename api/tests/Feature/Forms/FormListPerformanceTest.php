<?php

use Illuminate\Support\Facades\DB;

it('loads accurate counters only for the returned page', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $forms = collect(range(1, 3))->map(fn ($i) => $this->createForm($user, $workspace, [
        'updated_at' => now()->subMinutes($i),
        'max_submissions_count' => 2,
    ]));
    $target = $forms[2];
    foreach ($forms as $form) {
        DB::table('form_statistics')->insert([
            ['form_id' => $form->id, 'date' => '2026-01-01', 'data' => json_encode(['views' => 7])],
            ['form_id' => $form->id, 'date' => '2026-01-02', 'data' => json_encode(['views' => 5])],
        ]);
        DB::table('form_views')->insert(['form_id' => $form->id]);
        foreach (['completed', 'completed', 'partial'] as $status) {
            DB::table('form_submissions')->insert(['form_id' => $form->id, 'status' => $status, 'data' => '{}']);
        }
    }
    $deleted = $this->createForm($user, $workspace);
    $deleted->delete();
    $otherWorkspace = $this->createUserWorkspace($user);
    $this->createForm($user, $otherWorkspace);

    DB::enableQueryLog();
    DB::flushQueryLog();
    $response = $this->getJson(route('open.workspaces.forms.index', [
        'workspace' => $workspace->id, 'page' => 3, 'per_page' => 1,
    ]));
    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();

    $response->assertSuccessful()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $target->id)
        ->assertJsonPath('data.0.views_count', 13)
        ->assertJsonPath('data.0.submissions_count', 2)
        ->assertJsonPath('data.0.max_number_of_submissions_reached', true)
        ->assertJsonPath('meta.total', 3);

    $countQueries = $queries->filter(fn ($query) => str_contains($query['query'], 'form_statistics'));
    expect($countQueries)->toHaveCount(1);
    $sql = $countQueries->first()['query'];
    expect(strtolower($sql))->not->toContain('offset');
    expect($sql)->toContain('in ('.$target->id.')');
    // The paginated selection itself must not compute aggregate subqueries.
    $pageQuery = $queries->first(fn ($query) => str_contains(strtolower($query['query']), 'offset'));
    expect($pageQuery)->not->toBeNull();
    expect($pageQuery['query'])->not->toContain('form_statistics', 'form_submissions', 'form_views');
});

it('returns zero counters without statistics and skips count queries for empty pages', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $this->createForm($user, $workspace);
    $this->getJson(route('open.workspaces.forms.index', $workspace->id))
        ->assertSuccessful()->assertJsonPath('data.0.views_count', 0)
        ->assertJsonPath('data.0.submissions_count', 0);

    DB::enableQueryLog();
    DB::flushQueryLog();
    $response = $this->getJson(route('open.workspaces.forms.index', [
        'workspace' => $workspace->id, 'page' => 2,
    ]));
    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();
    $response->assertSuccessful()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 1);
    expect($queries->filter(fn ($query) => str_contains($query['query'], 'form_statistics')))->toHaveCount(0);
});

it('orders forms deterministically when edit timestamps are equal', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $timestamp = now()->startOfSecond();
    $first = $this->createForm($user, $workspace, ['updated_at' => $timestamp]);
    $second = $this->createForm($user, $workspace, ['updated_at' => $timestamp]);
    foreach ([1 => $second, 2 => $first] as $page => $form) {
        $this->getJson(route('open.workspaces.forms.index', [
            'workspace' => $workspace->id, 'page' => $page, 'per_page' => 1,
        ]))->assertSuccessful()->assertJsonPath('data.0.id', $form->id);
    }
});

it('indexes historical statistics by form', function () {
    expect(\Illuminate\Support\Facades\Schema::hasIndex('form_statistics', ['form_id']))->toBeTrue();
});
