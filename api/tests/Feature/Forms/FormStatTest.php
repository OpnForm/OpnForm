<?php

use Illuminate\Support\Facades\Artisan;

it('check formstat chart data', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, []);

    $views = [];
    $submissions = [];
    // Create 10 views & submissions for past days
    for ($i = 1; $i <= 10; $i++) {
        $date = now()->subDays($i);
        $dateString = $date->format('d-m-Y');

        $submission = $form->submissions()->create();
        $submission->created_at = $date;
        $submission->save();
        $view = $form->views()->create();
        $view->created_at = $date;
        $view->save();

        $views[$dateString] = isset($views[$dateString]) ? ($views[$dateString] + 1) : 1;
        $submissions[$dateString] = isset($submissions[$dateString]) ? ($submissions[$dateString] + 1) : 1;
    }

    // Run Command
    Artisan::call('forms:database-cleanup');

    // Create 5 views & submissions
    for ($i = 1; $i <= 5; $i++) {
        $form->views()->create();
        $form->submissions()->create();

        $dateString = now()->format('d-m-Y');
        $views[$dateString] = isset($views[$dateString]) ? ($views[$dateString] + 1) : 1;
        $submissions[$dateString] = isset($submissions[$dateString]) ? ($submissions[$dateString] + 1) : 1;
    }

    // Now check chart data
    $this->getJson(route('open.workspaces.form.stats', [$workspace, $form]) . '?date_from=' . now()->subDays(29)->format('Y-m-d') . '&date_to=' . now()->format('Y-m-d'))
        ->assertSuccessful()
        ->assertJson(function (\Illuminate\Testing\Fluent\AssertableJson $json) use ($views, $submissions) {
            return $json->whereType('views', 'array')
                ->whereType('submissions', 'array')
                ->where('views', function ($values) use ($views) {
                    foreach ($values as $date => $count) {
                        if ((isset($views[$date]) && $views[$date] != $count) || (!isset($views[$date]) && $count != 0)) {
                            return false;
                        }
                    }

                    return true;
                })
                ->where('submissions', function ($values) use ($submissions) {
                    foreach ($values as $date => $count) {
                        if ((isset($submissions[$date]) && $submissions[$date] != $count) || (!isset($submissions[$date]) && $count != 0)) {
                            return false;
                        }
                    }

                    return true;
                })
                ->etc();
        });
});


it('checks form stats details', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace, []);

    // Create form submissions with varying completion times
    $form->submissions()->createMany([
        ['completion_time' => 60],  // 1 minute
        ['completion_time' => 60],  // 1 minute
        ['completion_time' => 60],  // 1 minute
        ['completion_time' => 120], // 2 minutes
        ['completion_time' => 120], // 2 minutes
        [] // Incomplete submission
    ]);

    // Create form views with metadata for testing aggregation
    $form->views()->createMany([
        ['meta' => ['source' => 'Google', 'device' => 'Desktop', 'country' => 'US', 'browser' => 'Chrome', 'os' => 'Windows']],
        ['meta' => ['source' => 'Google', 'device' => 'Mobile', 'country' => 'US', 'browser' => 'Chrome', 'os' => 'Android']],
        ['meta' => ['source' => 'Facebook', 'device' => 'Desktop', 'country' => 'UK', 'browser' => 'Firefox', 'os' => 'MacOS']],
        ['meta' => ['source' => 'Direct', 'device' => 'Desktop', 'country' => 'CA', 'browser' => 'Safari', 'os' => 'MacOS']],
        ['meta' => ['source' => 'Google', 'device' => 'Tablet', 'country' => 'US', 'browser' => 'Safari', 'os' => 'iOS']],
        // Views with incomplete metadata (should default to 'Unknown')
        ['meta' => ['source' => null]],
        ['meta' => ['device' => null]],
        ['meta' => []],  // Empty metadata object
        ['meta' => null], // Null metadata
        []  // No metadata field at all
    ]);

    $this->getJson(route('open.workspaces.form.stats-details', [$workspace, $form]))
        ->assertSuccessful()
        ->assertJson(function (\Illuminate\Testing\Fluent\AssertableJson $json) {
            return $json->has('views')
                ->has('submissions')
                ->has('completion_rate')
                ->has('average_duration')
                ->has('meta_stats')
                ->where('views', 10)
                ->where('submissions', 6)
                ->where('completion_rate', 60)
                ->where('average_duration', '1m 24s')
                // Test metadata aggregation
                ->where('meta_stats.source.Google', 3)
                ->where('meta_stats.source.Facebook', 1)
                ->where('meta_stats.source.Direct', 1)
                ->where('meta_stats.source.Unknown', 5)
                ->where('meta_stats.device.Desktop', 3)
                ->where('meta_stats.device.Mobile', 1)
                ->where('meta_stats.device.Tablet', 1)
                ->where('meta_stats.device.Unknown', 5)
                ->where('meta_stats.country.US', 3)
                ->where('meta_stats.country.UK', 1)
                ->where('meta_stats.country.CA', 1)
                ->where('meta_stats.country.Unknown', 5)
                ->where('meta_stats.browser.Chrome', 2)
                ->where('meta_stats.browser.Firefox', 1)
                ->where('meta_stats.browser.Safari', 2)
                ->where('meta_stats.browser.Unknown', 5)
                ->where('meta_stats.os.Windows', 1)
                ->where('meta_stats.os.Android', 1)
                ->where('meta_stats.os.MacOS', 2)
                ->where('meta_stats.os.iOS', 1)
                ->where('meta_stats.os.Unknown', 5)
                ->etc();
        });
});
