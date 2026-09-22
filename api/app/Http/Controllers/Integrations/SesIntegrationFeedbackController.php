<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Service\Integrations\EmailDeliveryTracker;
use Aws\Sns\Message;
use Aws\Sns\MessageValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SesIntegrationFeedbackController extends Controller
{
    public function __invoke(Request $request, MessageValidator $validator, EmailDeliveryTracker $tracker)
    {
        abort_if(strlen($request->getContent()) > 300000, 413);
        $body = json_decode($request->getContent(), true);
        abort_unless(is_array($body), 422);
        abort_unless(in_array($body['TopicArn'] ?? '', config('email-tracking.sns_topics', []), true), 403);
        try {
            $validator->validate(new Message($body));
        } catch (\Throwable $e) {
            abort(403, 'Invalid SNS signature.');
        }
        if (($body['Type'] ?? '') === 'SubscriptionConfirmation') {
            // Construct the AWS endpoint ourselves; never follow a supplied SubscribeURL.
            preg_match('/^arn:aws:sns:([a-z0-9-]+):[0-9]{12}:[A-Za-z0-9_-]+$/', $body['TopicArn'], $matches);
            abort_unless(isset($matches[1]) && is_string($body['Token'] ?? null), 422);
            Http::timeout(10)->withOptions(['allow_redirects' => false])
                ->get('https://sns.'.$matches[1].'.amazonaws.com/', [
                    'Action' => 'ConfirmSubscription', 'Version' => '2010-03-31',
                    'TopicArn' => $body['TopicArn'], 'Token' => $body['Token'],
                ])->throw();
            return response()->json(['ok' => true]);
        }
        abort_unless(($body['Type'] ?? '') === 'Notification', 422);
        $payload = json_decode($body['Message'] ?? '', true);
        abort_unless(is_array($payload), 422);
        if (!$tracker->feedback($payload)) {
            Log::info('SES feedback did not match a retained integration email', ['sns_message_id' => $body['MessageId'] ?? null]);
        }
        return response()->json(['ok' => true]);
    }
}
