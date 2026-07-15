<?php

use App\Integrations\Data\SpreadsheetData;
use App\Integrations\Google\Google;
use App\Integrations\Google\Sheets\SpreadsheetManager;
use App\Models\Integration\FormIntegration;
use App\Models\OAuthProvider;

use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertSame;
use function PHPUnit\Framework\assertTrue;

test('build columns', function () {
    /** @var \App\Models\User $user */
    $user = $this->createUser();

    /** @var \App\Models\Workspace $workspace */
    $workspace = $this->createUserWorkspace($user);

    /** @var \App\Models\Forms $form */
    $form = $this->createForm($user, $workspace);

    /** @var \App\Models\OAuthProvider $provider */
    $provider = OAuthProvider::factory()
        ->for($user)
        ->create();

    /** @var FormIntegration $integration */
    $integration = FormIntegration::factory()
        ->for($form)
        ->for($provider, 'provider')
        ->create([
            'data' => new SpreadsheetData(
                url: 'https://google.com',
                spreadsheet_id: 'sp_test',
                columns: []
            )
        ]);

    $google = new Google($integration);

    $manager = new SpreadsheetManager($google, $integration);

    $columns = $manager->buildColumns();

    assertCount(14, $columns);

    foreach ($columns as $key => $column) {
        assertEquals($form->properties[$key]['id'], $column['id']);
        assertEquals($form->properties[$key]['name'], $column['name']);
    }
});

test('update columns', function () {
    /** @var \App\Models\User $user */
    $user = $this->createUser();

    /** @var \App\Models\Workspace $workspace */
    $workspace = $this->createUserWorkspace($user);

    /** @var \App\Models\Forms $form */
    $form = $this->createForm($user, $workspace);

    $form->update([
        'properties' => [
            ['id' => '000', 'name' => 'First', 'type' => 'text'],
            ['id' => '001', 'name' => 'Second', 'type' => 'text'],
        ]
    ]);

    /** @var \App\Models\OAuthProvider $provider */
    $provider = OAuthProvider::factory()
        ->for($user)
        ->create();

    /** @var FormIntegration $integration */
    $integration = FormIntegration::factory()
        ->for($form)
        ->for($provider, 'provider')
        ->create([
            'data' => new SpreadsheetData(
                url: 'https://google.com',
                spreadsheet_id: 'sp_test',
                columns: [
                    ['id' => '000', 'name' => 'First', 'type' => 'text'],
                    ['id' => '001', 'name' => 'Second', 'type' => 'text'],
                ]
            )
        ]);


    $google = new Google($integration);
    $manager = new SpreadsheetManager($google, $integration);

    $manager->buildColumns();

    $form->update([
        'properties' => [
            ['id' => '000', 'name' => 'First name', 'type' => 'text'],
            ['id' => '002', 'name' => 'Email', 'type' => 'text'],
        ]
    ]);

    $integration->refresh();
    $columns = $manager->buildColumns();

    assertCount(3, $columns);
    assertEquals('First name', $columns[0]['name']);
    assertEquals('Second', $columns[1]['name']);
    assertEquals('Email', $columns[2]['name']);
});

test('build row', function () {
    /** @var \App\Models\User $user */
    $user = $this->createUser();

    /** @var \App\Models\Workspace $workspace */
    $workspace = $this->createUserWorkspace($user);

    /** @var \App\Models\Forms $form */
    $form = $this->createForm($user, $workspace);

    $form->update([
        'properties' => [
            ['id' => '000', 'name' => 'First', 'type' => 'text'],
            ['id' => '001', 'name' => 'Second', 'type' => 'text'],
            ['id' => '002', 'name' => 'Third', 'type' => 'text'],
        ]
    ]);

    /** @var \App\Models\OAuthProvider $provider */
    $provider = OAuthProvider::factory()
        ->for($user)
        ->create();

    /** @var FormIntegration $integration */
    $integration = FormIntegration::factory()
        ->for($form)
        ->for($provider, 'provider')
        ->create([
            'data' => new SpreadsheetData(
                url: 'https://google.com',
                spreadsheet_id: 'sp_test',
                columns: [
                    ['id' => '000', 'name' => 'First'],
                    ['id' => '001', 'name' => 'Second'],
                    ['id' => '002', 'name' => 'Third'],
                ]
            )
        ]);


    $google = new Google($integration);
    $manager = new SpreadsheetManager($google, $integration);

    $submission = [
        '002' => 'Third value',
        '000' => 'First value',
    ];

    $row = $manager->buildRow($submission);

    assertSame(['First value', '', 'Third value'], $row);
});

test('build row uses the workspace policy for file links', function () {
    /** @var \App\Models\User $user */
    $user = $this->createUser();

    /** @var \App\Models\Workspace $workspace */
    $workspace = $this->createUserWorkspace($user);
    $workspace->update([
        'settings' => [
            'external_file_links' => [
                'expires_in_hours' => 72,
            ],
        ],
    ]);

    /** @var \App\Models\Forms\Form $form */
    $form = $this->createForm($user, $workspace, [
        'properties' => [
            ['id' => 'upload', 'name' => 'Upload', 'type' => 'files'],
        ],
    ]);
    $form->load('workspace');

    /** @var \App\Models\OAuthProvider $provider */
    $provider = OAuthProvider::factory()
        ->for($user)
        ->create();

    /** @var FormIntegration $integration */
    $integration = FormIntegration::factory()
        ->for($form)
        ->for($provider, 'provider')
        ->create([
            'data' => new SpreadsheetData(
                url: 'https://google.com',
                spreadsheet_id: 'sp_test',
                columns: [
                    ['id' => 'upload', 'name' => 'Upload'],
                ]
            )
        ]);

    $now = \Carbon\Carbon::parse('2026-07-17 17:00:00');
    \Carbon\Carbon::setTestNow($now);

    try {
        $manager = new SpreadsheetManager(new Google($integration), $integration);
        $row = $manager->buildRow(['upload' => ['weekend-upload.png']]);
    } finally {
        \Carbon\Carbon::setTestNow();
    }

    parse_str((string) parse_url($row[0], PHP_URL_QUERY), $queryParameters);

    assertSame($now->copy()->addHours(72)->timestamp, (int) $queryParameters['expires']);
    assertTrue(isset($queryParameters['signature']));
});
