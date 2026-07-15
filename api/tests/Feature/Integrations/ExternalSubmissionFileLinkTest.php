<?php

use App\Events\Forms\FormSubmitted;
use App\Integrations\Handlers\AbstractIntegrationHandler;
use App\Integrations\Handlers\DiscordIntegration;
use App\Integrations\Handlers\SlackIntegration;
use App\Integrations\Handlers\TelegramIntegration;
use App\Integrations\Handlers\ZapierIntegration;
use App\Models\Integration\FormIntegration;
use App\Service\Forms\FormSubmissionFormatter;
use Carbon\Carbon;

function queryParameterFromFileLink(string $url, string $key): string
{
    parse_str((string) parse_url($url, PHP_URL_QUERY), $queryParameters);

    return $queryParameters[$key] ?? '';
}

function formWithExternalFileLinkPolicy(object $test, int $expirationHours = 168): array
{
    $user = $test->actingAsProUser();
    $workspace = $test->createUserWorkspace($user);
    $workspace->update([
        'settings' => [
            'external_file_links' => [
                'expires_in_hours' => $expirationHours,
            ],
        ],
    ]);

    $form = $test->createForm($user, $workspace, [
        'properties' => [
            [
                'id' => 'attachment',
                'name' => 'Attachment',
                'type' => 'files',
                'required' => false,
            ],
        ],
    ]);
    $form->load('workspace');

    return [$form, ['attachment' => ['weekend-upload.png']]];
}

test('uses the configured workspace policy for signed submission file links', function () {
    [$form, $submissionData] = formWithExternalFileLinkPolicy($this, 168);
    $now = Carbon::parse('2026-07-17 17:00:00');
    Carbon::setTestNow($now);

    try {
        $fields = (new FormSubmissionFormatter($form, $submissionData))
            ->outputStringsOnly()
            ->useSignedUrlForFiles()
            ->getFieldsWithValue();
    } finally {
        Carbon::setTestNow();
    }

    $fileUrl = $fields[0]['value'];

    expect(queryParameterFromFileLink($fileUrl, 'expires'))
        ->toBe((string) $now->copy()->addHours(168)->timestamp);
    expect(queryParameterFromFileLink($fileUrl, 'signature'))->not->toBeEmpty();
});

test('uses the workspace policy in generic webhook and Zapier payloads', function (string $integrationClass) {
    [$form, $submissionData] = formWithExternalFileLinkPolicy($this, 72);
    $now = Carbon::parse('2026-07-17 17:00:00');
    Carbon::setTestNow($now);

    try {
        $payload = $integrationClass::formatWebhookData($form, $submissionData);
    } finally {
        Carbon::setTestNow();
    }

    $file = $payload['data']['attachment']['value']->first();

    expect(queryParameterFromFileLink($file['file_url'], 'signature'))->not->toBeEmpty();
    expect((int) queryParameterFromFileLink($file['file_url'], 'expires'))
        ->toBe($now->copy()->addHours(72)->timestamp);
})->with([
    'generic webhooks (Webhook, Activepieces and n8n)' => [AbstractIntegrationHandler::class],
    'Zapier' => [ZapierIntegration::class],
]);

test('signs file links in Slack, Discord and Telegram payloads', function (string $integrationClass, array $settings) {
    [$form, $submissionData] = formWithExternalFileLinkPolicy($this, 72);
    $integration = FormIntegration::factory()->make([
        'form_id' => $form->id,
        'data' => $settings,
    ]);

    $now = Carbon::parse('2026-07-17 17:00:00');
    Carbon::setTestNow($now);

    try {
        $handler = new $integrationClass(new FormSubmitted($form, $submissionData), $integration, ['name' => 'Chat']);
        $method = new ReflectionMethod($integrationClass, 'getWebhookData');
        $method->setAccessible(true);
        $payload = $method->invoke($handler);
    } finally {
        Carbon::setTestNow();
    }

    $payloadJson = html_entity_decode(json_encode($payload, JSON_UNESCAPED_SLASHES));
    $payloadJson = str_replace('\\', '', $payloadJson);

    expect($payloadJson)->toContain('signature=');
    expect(preg_match('/expires=(\d+)/', $payloadJson, $matches))->toBe(1);
    expect((int) $matches[1])->toBe($now->copy()->addHours(72)->timestamp);
})->with([
    'Slack' => [
        SlackIntegration::class,
        [
            'slack_webhook_url' => 'https://hooks.slack.com/services/test/test/test',
            'include_submission_data' => true,
        ],
    ],
    'Discord' => [
        DiscordIntegration::class,
        [
            'discord_webhook_url' => 'https://discord.com/api/webhooks/test/test',
            'include_submission_data' => true,
        ],
    ],
    'Telegram' => [
        TelegramIntegration::class,
        [
            'include_submission_data' => true,
        ],
    ],
]);
