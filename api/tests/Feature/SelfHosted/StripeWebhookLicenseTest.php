<?php

use App\Http\Controllers\Webhook\StripeController;
use App\Models\LicenseCheckoutSession;
use App\Models\LicenseKey;
use App\Models\User;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;

afterEach(function () {
    ApiRequestor::setHttpClient(CurlClient::instance());
});

function stripeWebhookControllerForTests(): StripeController
{
    return new class () extends StripeController {
        public function checkoutCompleted(array $payload)
        {
            return $this->handleCheckoutSessionCompleted($payload);
        }

        public function subscriptionUpdated(array $payload)
        {
            return $this->handleCustomerSubscriptionUpdated($payload);
        }
    };
}

describe('self-hosted Stripe checkout webhook', function () {
    it('returns a retryable failure and leaves the email unsent when license email delivery fails', function () {
        config(['app.self_hosted' => false]);

        LicenseCheckoutSession::create([
            'stripe_session_id' => 'cs_email_failure',
            'billing_email' => 'admin@company.com',
            'plan' => 'self_hosted',
            'period' => 'yearly',
            'status' => LicenseCheckoutSession::STATUS_PENDING,
            'expires_at' => now()->addMinutes(30),
        ]);

        ApiRequestor::setHttpClient(new class () implements ClientInterface {
            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
            {
                return [
                    json_encode([
                        'id' => 'sub_email_failure',
                        'object' => 'subscription',
                        'status' => 'active',
                        'current_period_end' => now()->addYear()->timestamp,
                    ]),
                    200,
                    [],
                ];
            }
        });

        $dispatcher = Mockery::mock(\Illuminate\Contracts\Notifications\Dispatcher::class);
        $dispatcher->shouldReceive('send')
            ->once()
            ->andThrow(new RuntimeException('Mail transport failed'));
        $this->app->instance(\Illuminate\Contracts\Notifications\Dispatcher::class, $dispatcher);

        $response = stripeWebhookControllerForTests()->checkoutCompleted([
            'data' => [
                'object' => [
                    'id' => 'cs_email_failure',
                    'customer' => 'cus_email_failure',
                    'subscription' => 'sub_email_failure',
                    'metadata' => ['type' => 'self_hosted_license'],
                ],
            ],
        ]);

        $checkoutSession = LicenseCheckoutSession::where('stripe_session_id', 'cs_email_failure')->first();

        expect($response->getStatusCode())->toBe(500);
        expect($checkoutSession->license_email_sent_at)->toBeNull();
        expect($checkoutSession->license_key_id)->not->toBeNull();
        expect(LicenseKey::where('stripe_subscription_id', 'sub_email_failure')->exists())->toBeTrue();
    });

    it('returns a retryable failure when the self-hosted checkout payload cannot be processed', function () {
        config(['app.self_hosted' => false]);

        LicenseCheckoutSession::create([
            'stripe_session_id' => 'cs_missing_subscription',
            'billing_email' => 'admin@company.com',
            'plan' => 'self_hosted',
            'period' => 'yearly',
            'status' => LicenseCheckoutSession::STATUS_PENDING,
            'expires_at' => now()->addMinutes(30),
        ]);

        $response = stripeWebhookControllerForTests()->checkoutCompleted([
            'data' => [
                'object' => [
                    'id' => 'cs_missing_subscription',
                    'customer' => 'cus_test',
                    'metadata' => ['type' => 'self_hosted_license'],
                ],
            ],
        ]);

        expect($response->getStatusCode())->toBe(500);
    });
});

describe('cloud Stripe subscription webhook', function () {
    it('keeps current product-split billing logic when selecting the main subscription item', function () {
        config([
            'app.self_hosted' => false,
            'pricing.test.pro.product_id' => 'prod_pro',
            'pricing.test.extra_user.product_id' => 'prod_extra_user',
        ]);

        $user = User::factory()->create([
            'stripe_id' => 'cus_cloud',
        ]);

        stripeWebhookControllerForTests()->subscriptionUpdated([
            'data' => [
                'object' => [
                    'id' => 'sub_cloud',
                    'customer' => 'cus_cloud',
                    'status' => 'active',
                    'metadata' => ['name' => 'default'],
                    'items' => [
                        'data' => [
                            [
                                'id' => 'si_extra',
                                'quantity' => 3,
                                'price' => [
                                    'id' => 'price_extra_user',
                                    'product' => 'prod_extra_user',
                                ],
                            ],
                            [
                                'id' => 'si_pro',
                                'quantity' => 1,
                                'price' => [
                                    'id' => 'price_pro',
                                    'product' => 'prod_pro',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $subscription = $user->subscriptions()->where('stripe_id', 'sub_cloud')->first();

        expect($subscription)->not->toBeNull();
        expect($subscription->type)->toBe('pro');
        expect($subscription->stripe_price)->toBe('price_pro');
        expect($subscription->items()->count())->toBe(2);
    });
});
