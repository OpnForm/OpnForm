<?php

use App\Models\Integration\FormIntegration;
use App\Models\Integration\FormIntegrationsEvent;
use App\Service\Integrations\EmailDeliveryTracker;
use Tests\TrackedEmailHarness;
use Aws\Sns\MessageValidator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(TestCase::class);

beforeEach(function () {
    // Isolate legacy suppression tables; individual tests install their own mail veto.
    Event::forget(\Illuminate\Mail\Events\MessageSending::class);
    Event::fake([\App\Events\Models\FormIntegrationsEventCreated::class]);
    Schema::create('form_integrations_events', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('integration_id');
        $table->string('status');
        $table->json('data');
        $table->timestamps();
    });
    (require database_path('migrations/2026_09_22_000000_add_tracking_id_to_form_integrations_events.php'))->up();
    $this->id = (string) Str::uuid();
    $this->event = FormIntegrationsEvent::createQuietly([
        'integration_id' => 1, 'tracking_id' => $this->id, 'status' => 'processing',
        'data' => ['email' => ['version' => 1, 'recipients' => []]],
    ]);
    $this->tracker = app(EmailDeliveryTracker::class);
    $this->recipients = $this->tracker->prepare($this->id, [' person@example.com ', 'other@example.com']);
    $this->recipientId = array_key_first($this->recipients);
    config(['email-tracking.sns_topics' => ['arn:aws:sns:eu-west-2:123456789012:Emails']]);
    Http::preventStrayRequests();
});

function emailFeedback($test, string $type = 'Delivery'): array
{
    return [
        'notificationType' => $type,
        'mail' => ['messageId' => 'ses-message-id', 'headers' => [
            ['name' => 'X-Form-Email-Event-ID', 'value' => $test->id],
            ['name' => 'X-Form-Email-Recipient-ID', 'value' => $test->recipientId],
        ]],
        lcfirst($type) => match ($type) {
            'Bounce' => ['bouncedRecipients' => [['emailAddress' => 'person@example.com']], 'bounceType' => 'Permanent', 'bounceSubType' => 'Suppressed'],
            'Complaint' => ['complainedRecipients' => [['emailAddress' => 'person@example.com']], 'complaintFeedbackType' => 'abuse'],
            default => ['recipients' => ['person@example.com']],
        },
    ];
}

function snsEmailEnvelope(array $payload): array
{
    return [
        'Type' => 'Notification', 'Message' => json_encode($payload), 'MessageId' => 'sns-id',
        'TopicArn' => 'arn:aws:sns:eu-west-2:123456789012:Emails',
        'Timestamp' => '2026-09-22T10:00:00Z', 'Signature' => 'invalid',
        'SignatureVersion' => '1', 'SigningCertURL' => 'https://sns.eu-west-2.amazonaws.com/SimpleNotificationService-test.pem',
    ];
}

it('retains invalid recipients and never calls an empty list successful', function () {
    $recipients = $this->tracker->prepare($this->id, ['bad', ' person@example.com ', 'person@example.com']);
    expect($recipients)->toHaveCount(2);
    expect($this->event->fresh()->status)->toBe('error');
    $this->tracker->prepare($this->id, ['']);
    expect($this->event->fresh()->status)->toBe('error');
});

it('captures the SES message ID rather than the MIME id and records SMTP acceptance', function ($transport, $sesId, $expectedStatus) {
    $email = (new \Symfony\Component\Mime\Email())->from('sender@example.com')->to('person@example.com')->text('test');
    if ($sesId) {
        $email->getHeaders()->addTextHeader('X-SES-Message-ID', $sesId);
    }
    $response = new \Illuminate\Mail\SentMessage(new \Symfony\Component\Mailer\SentMessage($email, \Symfony\Component\Mailer\Envelope::create($email)));
    $notification = new class () extends \Illuminate\Notifications\Notification {
        public array $emailTracking;
        public string $emailTransport;
    };
    $notification->emailTracking = ['event' => $this->id, 'recipient' => $this->recipientId];
    $notification->emailTransport = $transport;
    $this->tracker->handle(new NotificationSent(new stdClass(), $notification, 'mail', $response));
    $recipient = data_get($this->event->fresh()->data, 'email.recipients.'.$this->recipientId);
    expect($recipient->status)->toBe($expectedStatus)->and($recipient->provider_message_id)->toBe($sesId);
})->with([['ses', 'ses-message-id', 'accepted'], ['smtp', null, 'accepted'], ['log', null, 'not_sent']]);

it('does not treat a cancelled mail send as success', function () {
    $notification = new class () extends \Illuminate\Notifications\Notification {
        public array $emailTracking;
    };
    $notification->emailTracking = ['event' => $this->id, 'recipient' => $this->recipientId];
    $this->tracker->handle(new NotificationSent(new stdClass(), $notification, 'mail', null));
    expect(data_get($this->event->fresh()->data, 'email.recipients.'.$this->recipientId.'.status'))->toBe('blocked');
});

it('preserves early feedback and prevents duplicate or late delivery from erasing a bounce or complaint', function () {
    expect($this->tracker->feedback(emailFeedback($this, 'Bounce')))->toBeTrue();
    $this->tracker->recipient($this->id, $this->recipientId, ['status' => 'accepted', 'reason' => 'accepted']);
    $this->tracker->feedback(emailFeedback($this));
    $this->tracker->feedback(emailFeedback($this, 'Bounce'));
    expect(data_get($this->event->fresh()->data, 'email.recipients.'.$this->recipientId.'.status'))->toBe('bounced');
    $this->tracker->feedback(emailFeedback($this, 'Complaint'));
    $this->tracker->feedback(emailFeedback($this));
    expect(data_get($this->event->fresh()->data, 'email.recipients.'.$this->recipientId.'.status'))->toBe('complained');
    Event::assertDispatchedTimes(\App\Events\Models\FormIntegrationsEventCreated::class, 1);
});

it('only reports full delivery when all recipients have delivery confirmation', function () {
    $this->tracker->feedback(emailFeedback($this));
    expect($this->event->fresh()->status)->toBe('processing');
    $other = array_key_last($this->recipients);
    $this->tracker->recipient($this->id, $other, ['status' => 'accepted']);
    expect($this->event->fresh()->status)->toBe('accepted');
    $payload = emailFeedback($this);
    $payload['mail']['headers'][1]['value'] = $other;
    $payload['delivery']['recipients'] = ['other@example.com'];
    $this->tracker->feedback($payload);
    expect($this->event->fresh()->status)->toBe('delivered');
});

it('rejects mismatched recipients and message IDs without changing the event', function () {
    $payload = emailFeedback($this);
    $payload['delivery']['recipients'] = ['stranger@example.com'];
    expect($this->tracker->feedback($payload))->toBeFalse();
    $this->tracker->recipient($this->id, $this->recipientId, ['provider_message_id' => 'different']);
    expect($this->tracker->feedback(emailFeedback($this)))->toBeFalse();
    expect($this->event->fresh()->status)->toBe('processing');
});

it('rejects unsigned and unauthorized SNS envelopes', function () {
    $payload = snsEmailEnvelope(emailFeedback($this));
    unset($payload['Signature']);
    $this->postJson('/aws/sns/ses/integration-events', $payload)->assertForbidden();
    $payload['TopicArn'] = 'arn:aws:sns:eu-west-2:999999999999:Attack';
    $this->postJson('/aws/sns/ses/integration-events', $payload)->assertForbidden();
    Http::assertNothingSent();
});

it('processes verified SNS envelopes including text plain AWS requests', function () {
    $validator = Mockery::mock(MessageValidator::class);
    $validator->shouldReceive('validate')->once();
    app()->instance(MessageValidator::class, $validator);
    $this->call('POST', '/aws/sns/ses/integration-events', [], [], [], ['CONTENT_TYPE' => 'text/plain'], json_encode(snsEmailEnvelope(emailFeedback($this))))->assertOk();
    expect(data_get($this->event->fresh()->data, 'email.recipients.'.$this->recipientId.'.status'))->toBe('delivered');
});

it('confirms only the allowlisted SNS topic without following the supplied URL', function () {
    $validator = Mockery::mock(MessageValidator::class);
    $validator->shouldReceive('validate')->once();
    app()->instance(MessageValidator::class, $validator);
    Http::fake(['https://sns.eu-west-2.amazonaws.com/*' => Http::response('ok')]);
    $body = snsEmailEnvelope([]);
    $body['Type'] = 'SubscriptionConfirmation';
    $body['Token'] = 'signed-token';
    $body['SubscribeURL'] = 'http://localhost/internal';
    $this->postJson('/aws/sns/ses/integration-events', $body)->assertOk();
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://sns.eu-west-2.amazonaws.com/'));
});

it('keeps a timeout visible and continues other recipients without replaying the accepted one', function () {
    $integration = new FormIntegration();
    $integration->id = 7;
    $call = 0;
    \Illuminate\Support\Facades\Notification::shouldReceive('send')->twice()->andReturnUsing(function ($notifiable, $notification) use (&$call) {
        $call++;
        if ($call === 1) {
            throw new RuntimeException('secret SMTP payload');
        }
        app(EmailDeliveryTracker::class)->recipient($notification->emailTracking['event'], $notification->emailTracking['recipient'], ['status' => 'accepted']);
    });
    (new TrackedEmailHarness($integration))->run();
    $event = FormIntegrationsEvent::where('integration_id', 7)->sole();
    $states = array_map(fn ($recipient) => $recipient->status, (array) $event->data->email->recipients);
    expect(array_values($states))->toBe(['unknown', 'accepted']);
    expect($event->status)->toBe('unknown')->and(json_encode($event->data))->not->toContain('secret SMTP payload');
});

it('records a notification veto instead of false success', function () {
    $integration = new FormIntegration();
    $integration->id = 7;
    \Illuminate\Support\Facades\Notification::shouldReceive('send')->twice()->andReturnNull();
    (new TrackedEmailHarness($integration))->run();
    expect(FormIntegrationsEvent::where('integration_id', 7)->sole()->status)->toBe('error');
});

it('verifies real SNS signatures and rejects tampering without certificate network access', function () {
    $key = openssl_pkey_new(['private_key_bits' => 2048]);
    $csr = openssl_csr_new(['commonName' => 'sns.eu-west-2.amazonaws.com'], $key);
    $cert = openssl_csr_sign($csr, null, $key, 1);
    openssl_x509_export($cert, $pem);
    app()->instance(MessageValidator::class, new MessageValidator(fn () => $pem));
    $body = snsEmailEnvelope(emailFeedback($this));
    $canonical = '';
    foreach (['Message', 'MessageId', 'Timestamp', 'TopicArn', 'Type'] as $field) {
        $canonical .= "$field\n{$body[$field]}\n";
    }
    openssl_sign($canonical, $signature, $key, OPENSSL_ALGO_SHA1);
    $body['Signature'] = base64_encode($signature);
    $this->postJson('/aws/sns/ses/integration-events', $body)->assertOk();
    $body['Message'] = '{}';
    $this->postJson('/aws/sns/ses/integration-events', $body)->assertForbidden();
});

it('records real Laravel SES transport acceptance and a MessageSending veto without sending externally', function () {
    $calls = 0;
    $ses = new \Aws\Ses\SesClient([
        'version' => 'latest', 'region' => 'eu-west-2',
        'credentials' => ['key' => 'local-test', 'secret' => 'local-test'],
        'handler' => function () use (&$calls) {
            $calls++;
            return \GuzzleHttp\Promise\Create::promiseFor(new \Aws\Result(['MessageId' => 'sdk-message-id']));
        },
    ]);
    config(['mail.mailers.tracking_test' => ['transport' => 'tracking_test']]);
    \Illuminate\Support\Facades\Mail::extend('tracking_test', fn () => new \Illuminate\Mail\Transport\SesTransport($ses));
    $notification = new class () extends \Illuminate\Notifications\Notification {
        public array $emailTracking;
        public string $emailTransport = 'ses';
        public function via($notifiable)
        {
            return ['mail'];
        }
        public function toMail($notifiable)
        {
            return (new \Illuminate\Notifications\Messages\MailMessage())->mailer('tracking_test')->subject('Local test')->line('Local only');
        }
    };
    $notification->emailTracking = ['event' => $this->id, 'recipient' => $this->recipientId];
    // Isolate unrelated suppression listeners requiring their own database tables.
    Event::forget(\Illuminate\Mail\Events\MessageSending::class);
    \Illuminate\Support\Facades\Notification::route('mail', 'person@example.com')->notify($notification);
    expect(data_get($this->event->fresh()->data, 'email.recipients.'.$this->recipientId.'.provider_message_id'))->toBe('sdk-message-id');
    Event::listen(\Illuminate\Mail\Events\MessageSending::class, fn () => false);
    $notification->emailTracking['recipient'] = array_key_last($this->recipients);
    \Illuminate\Support\Facades\Notification::route('mail', 'other@example.com')->notify($notification);
    expect($calls)->toBe(1);
    expect(data_get($this->event->fresh()->data, 'email.recipients.'.array_key_last($this->recipients).'.status'))->toBe('blocked');
});


it('distinguishes an explicit SES rejection from an uncertain transport failure without leaking raw diagnostics', function () {
    $aws = new \Aws\Exception\AwsException('private provider text', new \Aws\Command('SendRawEmail'), ['code' => 'MessageRejected']);
    $wrapped = new \Symfony\Component\Mailer\Exception\TransportException('private SMTP text', 0, $aws);
    $this->tracker->failure($this->id, $this->recipientId, $wrapped);
    $recipient = data_get($this->event->fresh()->data, 'email.recipients.'.$this->recipientId);
    expect($recipient->status)->toBe('rejected')->and($recipient->reason_code)->toBe('MessageRejected');
    expect(json_encode($recipient))->not->toContain('private');
    expect($this->event->fresh()->status)->toBe('error');
});

it('keeps confirmed acceptance when a later synchronous listener throws', function () {
    $this->tracker->recipient($this->id, $this->recipientId, ['status' => 'accepted', 'provider_message_id' => 'accepted-id']);
    $this->tracker->failure($this->id, $this->recipientId, new RuntimeException('Later listener failed'));
    $recipient = data_get($this->event->fresh()->data, 'email.recipients.'.$this->recipientId);
    expect($recipient->status)->toBe('accepted')->and($recipient->provider_message_id)->toBe('accepted-id');
});

it('does not let an unavailable alert queue interrupt other recipients', function () {
    Event::getFacadeRoot()->except([\App\Events\Models\FormIntegrationsEventCreated::class]);
    Event::forget(\App\Events\Models\FormIntegrationsEventCreated::class);
    Event::listen(\App\Events\Models\FormIntegrationsEventCreated::class, function () {
        throw new RuntimeException('Queue unavailable');
    });
    // Pending recipients must still be returned for dispatch despite the invalid-address alert.
    $recipients = $this->tracker->prepare($this->id, ['invalid', 'person@example.com']);
    expect($recipients)->toHaveCount(2)->and($this->event->fresh()->status)->toBe('error');
});

it('does not retry the sending job when tracking persistence fails after a possible send', function () {
    $integration = new FormIntegration();
    $integration->id = 7;
    $this->partialMock(EmailDeliveryTracker::class, function ($mock) {
        $mock->shouldReceive('failure')->once()->andThrow(new RuntimeException('Database unavailable'));
        $mock->shouldReceive('outcome')->once()->andThrow(new RuntimeException('Still unavailable'));
    });
    \Illuminate\Support\Facades\Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('Transport timeout'));
    expect(fn () => (new TrackedEmailHarness($integration))->run())->not->toThrow(Throwable::class);
    expect(FormIntegrationsEvent::where('integration_id', 7)->sole()->status)->toBe('processing');
});

it('does not refresh event timestamps for unmatched or duplicate feedback', function () {
    $this->tracker->feedback(emailFeedback($this));
    $before = $this->event->fresh()->getAttributes();
    $this->travel(20)->minutes();
    $this->tracker->feedback(emailFeedback($this));
    $payload = emailFeedback($this);
    $payload['delivery']['recipients'] = ['wrong@example.com'];
    $this->tracker->feedback($payload);
    expect($this->event->fresh()->getAttributes())->toBe($before);
});

it('preserves feedback identifiers and records acceptance when feedback wins the race', function () {
    $this->tracker->feedback(emailFeedback($this));
    $this->tracker->recipient($this->id, $this->recipientId, ['status' => 'accepted', 'provider_message_id' => null, 'provider' => 'unknown']);
    $recipient = data_get($this->event->fresh()->data, 'email.recipients.'.$this->recipientId);
    expect($recipient->status)->toBe('delivered')->and($recipient->provider_message_id)->toBe('ses-message-id')
        ->and($recipient->timeline->accepted)->not->toBeNull();
});

it('safely ignores malformed structured feedback', function ($bad) {
    $before = $this->event->fresh()->getAttributes();
    expect($this->tracker->feedback(array_replace_recursive(emailFeedback($this), $bad)))->toBeFalse();
    expect($this->event->fresh()->getAttributes())->toBe($before);
})->with([
    [['notificationType' => []]],
    [['mail' => ['headers' => 'not-an-array']]],
    [['delivery' => ['recipients' => 'not-an-array']]],
    [['mail' => ['messageId' => ['bad']]]],
]);

it('asks SNS to retry a temporary signing certificate download failure', function () {
    Http::fake(['https://sns.eu-west-2.amazonaws.com/*' => Http::response('', 503)]);
    $this->postJson('/aws/sns/ses/integration-events', snsEmailEnvelope(emailFeedback($this)))->assertStatus(503);
});


it('shows a known interruption immediately without erasing completed recipient evidence', function () {
    $this->tracker->recipient($this->id, $this->recipientId, ['status' => 'accepted']);
    $this->tracker->outcome($this->id, 'unknown', 'Processing interrupted.');
    expect($this->event->fresh()->status)->toBe('unknown');
    expect(data_get($this->event->fresh()->data, 'email.recipients.'.$this->recipientId.'.status'))->toBe('accepted');
});


it('rejects malformed optional SNS envelope fields before any certificate request', function () {
    $payload = snsEmailEnvelope(emailFeedback($this));
    $payload['Subject'] = ['not-a-string'];
    $this->postJson('/aws/sns/ses/integration-events', $payload)->assertForbidden();
    Http::assertNothingSent();
});
