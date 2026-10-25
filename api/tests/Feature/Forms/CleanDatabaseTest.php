<?php

use Illuminate\Support\Facades\Artisan;

it('check form statistic for views & submissions counts', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, []);

    // Create 10 views & submissions (in the past of 1 day so that it's cleaned)
    for ($i = 1; $i <= 10; $i++) {
        $submission = $form->submissions()->create();
        $submission->created_at = now()->subDay();
        $submission->save();
        $view = $form->views()->create();
        $view->created_at = now()->subDay();
        $view->save();
    }

    // Create a submission & a view for another date
    $submission = $form->submissions()->create();
    $submission->created_at = now()->subDays(2);
    $submission->save();
    $view = $form->views()->create();
    $view->created_at = now()->subDays(2);
    $view->save();

    // Run Command
    Artisan::call('forms:database-cleanup');

    // Create 5 views & submissions
    for ($i = 1; $i <= 5; $i++) {
        $form->views()->create();
        $form->submissions()->create();
    }

    // Now check counters
    $statistics = $form->statistics()->get();
    expect($form->views_count)->toBe(16);
    expect($form->submissions_count)->toBe(16);
    expect($form->views()->count())->toBe(5);
    expect($form->submissions()->count())->toBe(16);
    expect(count($statistics))->toBe(2); // 1 per day for 2 different dates
    expect($statistics[0]['date'])->toBe(now()->subDays(2)->toDateString());
    expect($statistics[0]['data']['views'])->toBe(1);
    expect($statistics[0]['data']['submissions'])->toBe(0);
    expect($statistics[1]['date'])->toBe(now()->subDay()->toDateString());
    expect($statistics[1]['data']['views'])->toBe(10);
    expect($statistics[1]['data']['submissions'])->toBe(0);
    foreach (['source', 'device', 'country', 'browser', 'os'] as $field) {
        expect($statistics[0]['data']['meta_stats'][$field])->toBe(['Unknown' => 1]);
        expect($statistics[1]['data']['meta_stats'][$field])->toBe(['Unknown' => 10]);
    }
});
