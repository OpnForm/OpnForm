<?php

use App\Events\Forms\FormSubmitted;
use App\Integrations\Handlers\DiscordIntegration;
use App\Integrations\Handlers\SlackIntegration;
use App\Models\Integration\FormIntegration;

test('slack integration escapes submission values in mrkdwn blocks', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $textField = collect($form->properties)->firstWhere('name', 'Name');
    $submissionData = [
        $textField['id'] => '<http://evil.test|Click me>',
    ];

    $integration = FormIntegration::factory()->make([
        'form_id' => $form->id,
        'integration_id' => 'slack',
        'data' => [
            'slack_webhook_url' => 'https://hooks.slack.com/services/test/test/test',
            'message' => 'Hello <span mention="true" mention-field-id="' . $textField['id'] . '"></span>',
            'include_submission_data' => true,
        ],
    ]);

    $handler = new class (new FormSubmitted($form, $submissionData), $integration, ['name' => 'Slack']) extends SlackIntegration {
        public function payload(): array
        {
            return $this->getWebhookData();
        }
    };

    $payload = $handler->payload();
    $text = $payload['blocks'][0]['text']['text'] . "\n" . $payload['blocks'][1]['text']['text'];

    expect($text)->toContain('&lt;http://evil.test|Click me&gt;');
    expect($text)->not->toContain('<http://evil.test|Click me>');
});

test('discord integration escapes submission values in content and embeds', function () {
    $user = $this->actingAsProUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $textField = collect($form->properties)->firstWhere('name', 'Name');
    $submissionData = [
        $textField['id'] => '[@everyone](https://evil.test)',
    ];

    $integration = FormIntegration::factory()->make([
        'form_id' => $form->id,
        'integration_id' => 'discord',
        'data' => [
            'discord_webhook_url' => 'https://discord.com/api/webhooks/test/test',
            'message' => 'Hello <span mention="true" mention-field-id="' . $textField['id'] . '"></span>',
            'include_submission_data' => true,
        ],
    ]);

    $handler = new class (new FormSubmitted($form, $submissionData), $integration, ['name' => 'Discord']) extends DiscordIntegration {
        public function payload(): array
        {
            return $this->getWebhookData();
        }
    };

    $payload = $handler->payload();

    expect($payload['content'])->toContain('\\[@​everyone\\]\\(https://evil.test\\)');
    expect($payload['content'])->not->toContain('[@everyone](https://evil.test)');
    expect($payload['embeds'][0]['description'])->toContain('\\[@​everyone\\]\\(https://evil.test\\)');
});
