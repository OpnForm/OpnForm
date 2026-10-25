<?php

use App\Models\Forms\FormStatistic;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->travelTo(now()->startOfDay()->addHours(12));
    $user = $this->actingAsProUser();
    $this->workspace = $this->createUserWorkspace($user);
    $this->form = $this->createForm($user, $this->workspace);
    $this->statsUrl = route('open.workspaces.form.stats-details', [$this->workspace, $this->form]);
});

it('preserves traffic breakdown across repeated daily cleanups and combines recent views', function () {
    $meta = ['source' => 'Google', 'device' => 'Desktop', 'country' => 'FR', 'browser' => 'Firefox', 'os' => 'Linux'];
    foreach ([now()->subDay(), now()->subDay(), now()->subMinute()] as $date) {
        $view = $this->form->views()->create(['meta' => $meta]);
        $view->forceFill(['created_at' => $date])->save();
    }
    $this->getJson($this->statsUrl)->assertSuccessful()->assertJsonPath('meta_stats.source.Google', 3);

    Artisan::call('forms:database-cleanup');
    expect($this->form->views()->count())->toBe(0);
    expect($this->form->statistics()->count())->toBe(2);
    Cache::forget('form_stats_metadata_v2_' . $this->form->id);
    foreach ($meta as $field => $value) {
        $this->getJson($this->statsUrl)->assertSuccessful()->assertJsonPath('meta_stats.' . $field . '.' . $value, 3);
    }

    $view = $this->form->views()->create(['meta' => $meta]);
    $view->forceFill(['created_at' => now()->subSecond()])->save();
    Artisan::call('forms:database-cleanup');
    // The cleanup invalidates the previously cached result.
    $this->getJson($this->statsUrl)->assertSuccessful()->assertJsonPath('meta_stats.source.Google', 4);
    Artisan::call('forms:database-cleanup');
    expect($this->form->statistics()->get()->sum(fn ($row) => $row->data['views']))->toBe(4);

    $this->form->views()->create(['meta' => $meta]);
    $this->form->views()->create(['meta' => ['source' => 'Direct']]);
    Cache::forget('form_stats_metadata_v2_' . $this->form->id);
    $response = $this->getJson($this->statsUrl)->assertSuccessful()
        ->assertJsonPath('meta_stats.source.Google', 5)
        ->assertJsonPath('meta_stats.source.Direct', 1)
        ->assertJsonPath('meta_stats.device.Unknown', 1);
    expect(array_keys($response->json('meta_stats.source')))->toBe(['Google', 'Direct']);
});

it('normalizes missing metadata and leaves legacy statistics and other forms isolated', function () {
    $this->form->statistics()->create([
        'date' => now()->subDay()->toDateString(),
        'data' => ['views' => 50, 'submissions' => 7, 'custom' => 'preserved'],
    ]);
    foreach ([null, [], ['source' => null], ['source' => ''], ['source' => 'null']] as $meta) {
        $view = $this->form->views()->create(['meta' => $meta]);
        $view->forceFill(['created_at' => now()->subDay()])->save();
    }
    $otherForm = $this->createForm($this->form->creator, $this->workspace);
    $view = $otherForm->views()->create(['meta' => ['source' => 'Other']]);
    $view->forceFill(['created_at' => now()->subDay()])->save();

    Artisan::call('forms:database-cleanup');
    $data = $this->form->statistics()->first()->data;
    expect($data['views'])->toBe(55);
    expect($data['submissions'])->toBe(7);
    expect($data['custom'])->toBe('preserved');
    $response = $this->getJson($this->statsUrl)->assertSuccessful();
    foreach (['source', 'device', 'country', 'browser', 'os'] as $field) {
        expect($response->json('meta_stats.' . $field))->toBe(['Unknown' => 5]);
    }
});

it('keeps views at the cutoff and rolls back aggregation if storing statistics fails', function () {
    $oldView = $this->form->views()->create(['meta' => ['source' => 'Google']]);
    $oldView->forceFill(['created_at' => now()->subDay()])->save();
    $olderView = $this->form->views()->create(['meta' => ['source' => 'Google']]);
    $olderView->forceFill(['created_at' => now()->subDays(2)])->save();
    $recentView = $this->form->views()->create(['meta' => ['source' => 'Direct']]);

    $writes = 0;
    FormStatistic::creating(function () use (&$writes) {
        if (++$writes === 2) {
            throw new RuntimeException('Storage failed');
        }
    });
    try {
        expect(fn () => Artisan::call('forms:database-cleanup'))->toThrow(RuntimeException::class, 'Storage failed');
        expect($this->form->views()->count())->toBe(3);
        expect($this->form->statistics()->count())->toBe(0);
    } finally {
        FormStatistic::flushEventListeners();
    }

    Artisan::call('forms:database-cleanup');
    expect($this->form->views()->pluck('id')->all())->toBe([$recentView->id]);
    $this->getJson($this->statsUrl)->assertSuccessful()
        ->assertJsonPath('meta_stats.source.Google', 2)
        ->assertJsonPath('meta_stats.source.Direct', 1);
});

it('does not delete a backdated view inserted during aggregation', function () {
    $view = $this->form->views()->create(['meta' => ['source' => 'Google']]);
    $view->forceFill(['created_at' => now()->subDay()])->save();
    FormStatistic::created(function () {
        $view = $this->form->views()->create(['meta' => ['source' => 'Direct']]);
        $view->forceFill(['created_at' => now()->subDay()])->save();
    });
    try {
        Artisan::call('forms:database-cleanup');
    } finally {
        FormStatistic::flushEventListeners();
    }

    expect($this->form->views()->count())->toBe(1);
    $this->getJson($this->statsUrl)->assertSuccessful()
        ->assertJsonPath('meta_stats.source.Google', 1)
        ->assertJsonPath('meta_stats.source.Direct', 1);
});

it('returns empty breakdowns for forms without metadata history or views', function () {
    $this->form->statistics()->create(['date' => now()->toDateString(), 'data' => ['views' => 50]]);
    $response = $this->getJson($this->statsUrl)->assertSuccessful();
    foreach (['source', 'device', 'country', 'browser', 'os'] as $field) {
        expect($response->json('meta_stats.' . $field))->toBe([]);
    }
});

it('preserves an earlier view that becomes visible after aggregation', function () {
    $pendingView = $this->form->views()->create(['meta' => ['source' => 'Direct']]);
    $this->form->views()->whereKey($pendingView->id)->delete();
    $view = $this->form->views()->create(['meta' => ['source' => 'Google']]);
    $view->forceFill(['created_at' => now()->subDay()])->save();

    // Simulate a previously allocated ID committing after the aggregate read.
    FormStatistic::created(function () use ($pendingView) {
        $this->form->views()->insert([
            'id' => $pendingView->id,
            'form_id' => $this->form->id,
            'meta' => json_encode(['source' => 'Direct']),
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
    });
    try {
        Artisan::call('forms:database-cleanup');
    } finally {
        FormStatistic::flushEventListeners();
    }

    expect($this->form->views()->count())->toBe(0);
    expect($this->form->statistics()->first()->data['views'])->toBe(2);
    $this->getJson($this->statsUrl)->assertSuccessful()
        ->assertJsonPath('meta_stats.source.Google', 1)
        ->assertJsonPath('meta_stats.source.Direct', 1);
});

it('processes multiple bounded batches without losing or duplicating counts', function () {
    $rows = array_fill(0, 1005, [
        'form_id' => $this->form->id,
        'meta' => json_encode(['source' => 'Google']),
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);
    $this->form->views()->insert($rows);

    Artisan::call('forms:database-cleanup');
    Artisan::call('forms:database-cleanup');

    expect($this->form->views()->count())->toBe(0);
    expect($this->form->statistics()->count())->toBe(1);
    expect($this->form->statistics()->first()->data['views'])->toBe(1005);
    $this->getJson($this->statsUrl)->assertSuccessful()
        ->assertJsonPath('meta_stats.source.Google', 1005)
        ->assertJsonPath('meta_stats.device.Unknown', 1005);
});
