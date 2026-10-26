<?php

use Illuminate\Queue\Console\WorkCommand;
use Illuminate\Queue\Worker;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

it('uses the producer queue configuration for the hosted supervisor worker', function (string $queue) {
    config(['queue.connections.redis.queue' => $queue]);
    $worker = Mockery::mock(Worker::class);
    $worker->shouldReceive('setName')->once()->andReturnSelf();
    $worker->shouldReceive('setCache')->once()->andReturnSelf();
    $worker->shouldReceive('daemon')->once()->with('redis', $queue, Mockery::any())->andReturn(0);

    $configuration = file_get_contents(base_path('../deploy/scaleway/supervisor-jobs.conf'));
    preg_match('/^command=php \/app\/artisan queue:work (.+)$/m', $configuration, $matches);
    $arguments = str_replace('%(ENV_WORKER_TRIES)s', '3', $matches[1]);
    $command = new WorkCommand($worker, Cache::store());
    $command->setLaravel(app());
    $console = new Application();
    $console->setAutoExit(false);
    $console->add($command);

    expect($console->run(new StringInput('queue:work '.$arguments), new BufferedOutput()))->toBe(0);
})->with(['default', 'custom-fixture']);
