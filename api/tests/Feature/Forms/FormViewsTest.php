<?php

use App\Jobs\Form\ResolveFormViewCountry;
use App\Models\Forms\FormView;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Queue\SyncQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('can see form without counting view for form owner', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $this->getJson(route('forms.view', $form->slug))
        ->assertSuccessful();

    expect($form->views()->count())->toBe(0);
});

it('can see form and count view for guest', function () {
    $user = $this->actingAsUser();
    $workspace = $this->createUserWorkspace($user);
    $form = $this->createForm($user, $workspace);

    $this->actingAsGuest();

    $this->getJson(route('forms.view', $form->slug))
        ->assertSuccessful();

    expect($form->views()->count())->toBe(1);
});

function viewedFormAsGuest($test, string $ip)
{
    $user = $test->actingAsUser();
    $form = $test->createForm($user, $test->createUserWorkspace($user));
    $test->actingAsGuest();

    $test->withServerVariables(['REMOTE_ADDR' => $ip])
        ->getJson(route('forms.view', $form->slug))
        ->assertSuccessful();

    return $form;
}

it('saves the view as Unknown and queues the country lookup', function (string $ip, string $queued) {
    config(['queue.default' => 'redis', 'services.ip_location.provider' => 'internetdata']);
    Queue::fake();
    Http::fake();

    $form = viewedFormAsGuest($this, $ip);

    $view = $form->views()->sole();
    expect($view->meta['country'])->toBe('Unknown');
    Queue::assertPushed(ResolveFormViewCountry::class, fn ($job) => $job->viewId === $view->id && $job->ip === $queued);
    Http::assertNothingSent();
})->with([
    'IPv4' => ['8.8.8.8', '8.8.8.8'],
    'IPv4-mapped' => ['::ffff:8.8.8.8', '8.8.8.8'],
]);

it('saves the view as Unknown without a lookup for a non-public address', function (string $ip) {
    config(['queue.default' => 'redis', 'services.ip_location.provider' => 'internetdata']);
    Queue::fake();
    Http::fake();

    $form = viewedFormAsGuest($this, $ip);

    expect($form->views()->sole()->meta['country'])->toBe('Unknown');
    Queue::assertNotPushed(ResolveFormViewCountry::class);
    Http::assertNothingSent();
})->with(['192.168.1.1', '100.64.0.1', '224.0.0.1', '::ffff:10.0.0.1', 'ff02::1']);

it('saves the view as Unknown without a lookup when lookups are off', function () {
    config(['queue.default' => 'redis', 'services.ip_location.provider' => 'none']);
    Queue::fake();
    Http::fake();

    $form = viewedFormAsGuest($this, '8.8.8.8');

    expect($form->views()->sole()->meta['country'])->toBe('Unknown');
    Queue::assertNotPushed(ResolveFormViewCountry::class);
    Http::assertNothingSent();
});

it('saves the view as Unknown without a lookup on the sync queue', function () {
    config(['queue.default' => 'sync', 'services.ip_location.provider' => 'internetdata']);
    Http::fake();

    $form = viewedFormAsGuest($this, '8.8.8.8');

    expect($form->views()->sole()->meta['country'])->toBe('Unknown');
    Http::assertNothingSent();
});

function unknownView($test)
{
    $user = $test->actingAsUser();
    $form = $test->createForm($user, $test->createUserWorkspace($user));

    return $form->views()->create(['meta' => [
        'ip' => hash('sha256', 'x'), 'source' => 'Direct', 'device' => 'Desktop', 'browser' => 'Firefox', 'os' => 'Linux', 'country' => 'Unknown',
    ]]);
}

it('sets the country on the existing view, and a retry never counts it twice', function () {
    config(['services.ip_location.provider' => 'internetdata']);
    Http::fake(['api.internetdata.io/*' => Http::response(['ip' => '8.8.8.8', 'location' => ['country' => 'US', 'country_name' => 'United States']], 200)]);
    $view = unknownView($this);

    $job = new ResolveFormViewCountry($view->id, '8.8.8.8');
    app()->call([$job, 'handle']);
    app()->call([$job, 'handle']);

    expect(FormView::count())->toBe(1)
        ->and($view->fresh()->meta)->toMatchArray(['country' => 'United States', 'browser' => 'Firefox', 'source' => 'Direct']);
    Http::assertSentCount(1);
});

it('leaves the view Unknown when the lookup fails', function () {
    config(['services.ip_location.provider' => 'internetdata']);
    Http::fake(['api.internetdata.io/*' => Http::response('Service Unavailable', 503)]);
    $view = unknownView($this);

    app()->call([new ResolveFormViewCountry($view->id, '8.8.8.8'), 'handle']);

    expect($view->fresh()->meta['country'])->toBe('Unknown');
});

it('does nothing once the view has been aggregated', function () {
    config(['services.ip_location.provider' => 'internetdata']);
    Http::fake(['api.internetdata.io/*' => Http::response(['ip' => '8.8.8.8', 'location' => ['country' => 'US', 'country_name' => 'United States']], 200)]);
    $view = unknownView($this);
    $view->delete();

    app()->call([new ResolveFormViewCountry($view->id, '8.8.8.8'), 'handle']);

    expect(FormView::count())->toBe(0);
});

it('encrypts the queued view ID and IP', function () {
    $queue = new class () extends SyncQueue {
        public function payloadFor($job): string
        {
            return $this->createPayload($job, 'default');
        }
    };
    $queue->setContainer(app());

    expect(new ResolveFormViewCountry(1, '8.8.8.8'))->toBeInstanceOf(ShouldBeEncrypted::class)
        ->and($queue->payloadFor(new ResolveFormViewCountry(1, '8.8.8.8')))->not->toContain('8.8.8.8');
});
