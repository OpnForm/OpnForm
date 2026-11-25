<?php

use App\Jobs\Form\ResolveFormViewCountry;
use App\Models\Forms\FormView;
use App\Service\Forms\Analytics\IpLocation\IpLocationHttpClient;
use App\Service\Forms\Analytics\IpLocationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
    app(IpLocationHttpClient::class)->fake();

    $form = viewedFormAsGuest($this, $ip);

    $view = $form->views()->sole();
    expect($view->meta['country'])->toBe('Unknown');
    Queue::assertPushedOn(ResolveFormViewCountry::QUEUE, ResolveFormViewCountry::class, fn ($job) => $job->viewId === $view->id && $job->ip === $queued);
    app(IpLocationHttpClient::class)->assertNothingSent();
})->with([
    'IPv4' => ['8.8.8.8', '8.8.8.8'],
    'IPv4-mapped' => ['::ffff:8.8.8.8', '8.8.8.8'],
]);

it('saves the view as Unknown without a lookup for a non-public address', function (string $ip) {
    config(['queue.default' => 'redis', 'services.ip_location.provider' => 'internetdata']);
    Queue::fake();
    app(IpLocationHttpClient::class)->fake();

    $form = viewedFormAsGuest($this, $ip);

    expect($form->views()->sole()->meta['country'])->toBe('Unknown');
    Queue::assertNotPushed(ResolveFormViewCountry::class);
    app(IpLocationHttpClient::class)->assertNothingSent();
})->with(['192.168.1.1', '100.64.0.1', '224.0.0.1', '::ffff:10.0.0.1', 'ff02::1']);

it('saves the view as Unknown without a lookup when lookups are off', function () {
    config(['queue.default' => 'redis', 'services.ip_location.provider' => 'none']);
    Queue::fake();
    app(IpLocationHttpClient::class)->fake();

    $form = viewedFormAsGuest($this, '8.8.8.8');

    expect($form->views()->sole()->meta['country'])->toBe('Unknown');
    Queue::assertNotPushed(ResolveFormViewCountry::class);
    app(IpLocationHttpClient::class)->assertNothingSent();
});

it('saves the view as Unknown without a lookup on the sync queue', function () {
    config(['queue.default' => 'sync', 'services.ip_location.provider' => 'internetdata']);
    app(IpLocationHttpClient::class)->fake();

    $form = viewedFormAsGuest($this, '8.8.8.8');

    expect($form->views()->sole()->meta['country'])->toBe('Unknown');
    app(IpLocationHttpClient::class)->assertNothingSent();
});

it('records the view when the lookup cannot be queued', function () {
    app(IpLocationHttpClient::class)->fake();

    $user = $this->actingAsUser();
    $form = $this->createForm($user, $this->createUserWorkspace($user));
    $this->actingAsGuest();
    config(['queue.default' => 'redis', 'services.ip_location.provider' => 'internetdata']);
    Log::spy();
    Queue::shouldReceive('connection')->once()->andThrow(new RuntimeException('Queue unavailable'));

    $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
        ->getJson(route('forms.view', $form->slug))
        ->assertSuccessful();

    $view = $form->views()->sole();
    expect($view->meta['country'])->toBe('Unknown');
    Log::shouldHaveReceived('warning')->with('Could not schedule form view country enrichment', [
        'view_id' => $view->id,
        'error' => RuntimeException::class,
    ])->once();
    app(IpLocationHttpClient::class)->assertNothingSent();
});

it('uses the country cache without adding work to either queue', function (int $status, string $expectedCountry) {
    config(['queue.default' => 'redis', 'services.ip_location.provider' => 'internetdata']);
    Queue::fake();
    $http = app(IpLocationHttpClient::class);
    $http->preventStrayRequests()->fake([
        'api.internetdata.io/*' => Http::response(['location' => ['country' => 'US', 'country_name' => 'United States']], $status),
    ]);
    app(IpLocationService::class)->lookup('8.8.8.8');

    $form = viewedFormAsGuest($this, '8.8.8.8');

    expect($form->views()->sole()->meta['country'])->toBe($expectedCountry);
    Queue::assertNotPushed(ResolveFormViewCountry::class);
    $http->assertSentCount(1);
})->with([
    'successful lookup' => [200, 'United States'],
    'failed lookup' => [503, 'Unknown'],
]);

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
    app(IpLocationHttpClient::class)->fake(['api.internetdata.io/*' => Http::response(['ip' => '8.8.8.8', 'location' => ['country' => 'US', 'country_name' => 'United States']], 200)]);
    $view = unknownView($this);

    $job = new ResolveFormViewCountry($view->id, '8.8.8.8');
    app()->call([$job, 'handle']);
    app()->call([$job, 'handle']);

    expect(FormView::count())->toBe(1)
        ->and($view->fresh()->meta)->toMatchArray(['country' => 'United States', 'browser' => 'Firefox', 'source' => 'Direct']);
    app(IpLocationHttpClient::class)->assertSentCount(1);
});

it('leaves the view Unknown when the lookup fails', function () {
    config(['services.ip_location.provider' => 'internetdata']);
    app(IpLocationHttpClient::class)->fake(['api.internetdata.io/*' => Http::response('Service Unavailable', 503)]);
    $view = unknownView($this);

    app()->call([new ResolveFormViewCountry($view->id, '8.8.8.8'), 'handle']);

    expect($view->fresh()->meta['country'])->toBe('Unknown');
});

it('does nothing once the view has been aggregated', function () {
    config(['services.ip_location.provider' => 'internetdata']);
    app(IpLocationHttpClient::class)->fake(['api.internetdata.io/*' => Http::response(['ip' => '8.8.8.8', 'location' => ['country' => 'US', 'country_name' => 'United States']], 200)]);
    $view = unknownView($this);
    $this->travel(6)->minutes();
    Artisan::call('forms:database-cleanup');

    app()->call([new ResolveFormViewCountry($view->id, '8.8.8.8'), 'handle']);

    expect(FormView::count())->toBe(0);
    app(IpLocationHttpClient::class)->assertNothingSent();
});

it('lets a recently saved view be enriched before aggregating it', function () {
    config(['services.ip_location.provider' => 'internetdata']);
    app(IpLocationHttpClient::class)->fake([
        'api.internetdata.io/*' => Http::response(['location' => ['country' => 'US', 'country_name' => 'United States']]),
    ]);
    $view = unknownView($this);
    $form = $view->form;
    $this->travel(1)->minute();

    Artisan::call('forms:database-cleanup');
    expect($form->views()->sole()->id)->toBe($view->id);

    app()->call([new ResolveFormViewCountry($view->id, '8.8.8.8'), 'handle']);
    $this->travel(5)->minutes();
    Artisan::call('forms:database-cleanup');

    expect($form->views()->count())->toBe(0)
        ->and($form->statistics()->sole()->data['meta_stats']['country'])->toBe(['United States' => 1]);
});

it('never sends an IP when the enrichment window has elapsed', function () {
    config(['services.ip_location.provider' => 'internetdata']);
    app(IpLocationHttpClient::class)->fake();
    $view = unknownView($this);
    $this->travel(5)->minutes();

    app()->call([new ResolveFormViewCountry($view->id, '8.8.8.8'), 'handle']);

    expect($view->fresh()->meta['country'])->toBe('Unknown');
    app(IpLocationHttpClient::class)->assertNothingSent();
});

it('processes an encrypted view payload through the database worker', function () {
    $user = $this->actingAsUser();
    $form = $this->createForm($user, $this->createUserWorkspace($user));
    $this->actingAsGuest();
    config(['queue.default' => 'database', 'services.ip_location.provider' => 'internetdata']);
    $http = app(IpLocationHttpClient::class);
    $http->preventStrayRequests()->fake([
        'api.internetdata.io/*' => Http::response(['location' => ['country' => 'US', 'country_name' => 'United States']]),
    ]);

    $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
        ->getJson(route('forms.view', $form->slug))
        ->assertSuccessful();

    $view = $form->views()->sole();
    expect($view->meta['country'])->toBe('Unknown');
    $queued = DB::table('jobs')->sole();
    expect($queued->queue)->toBe(ResolveFormViewCountry::QUEUE)
        ->and($queued->payload)->not->toContain('8.8.8.8');
    expect(Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--sleep' => 0]))->toBe(0);
    expect($view->fresh()->meta['country'])->toBe('Unknown');
    $http->assertNothingSent();
    expect(Artisan::call('queue:work', [
        'connection' => 'database', '--queue' => ResolveFormViewCountry::QUEUE, '--once' => true,
    ]))->toBe(0);
    expect($view->fresh()->meta['country'])->toBe('United States');
    expect(DB::table('jobs')->count())->toBe(0);
    $http->assertSentCount(1);
});
