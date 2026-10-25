<?php

require '/app/vendor/autoload.php';
require __DIR__.'/HostedStateBootstrap.php';
bootHostedStateFixture(false);
$snapshot = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
try {
    app(App\Service\Deployment\DynamoStateImporter::class)->import($snapshot, function ($index) use ($argv) {
        if ($index === 1 && ($argv[3] ?? '') === 'pause') {
            file_put_contents($argv[2], 'first-atomic-commit-acknowledged');
            while (true) {
                usleep(10000);
            }
        }
    });
    exit(0);
} catch (RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(78);
}
