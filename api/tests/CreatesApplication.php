<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use RuntimeException;

trait CreatesApplication
{
    /**
     * @var array{private: string, public: string}|null
     */
    private static ?array $passportTestKeys = null;

    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();
        $this->configurePassportTestKeys($app);

        return $app;
    }

    private function configurePassportTestKeys(Application $app): void
    {
        if (self::$passportTestKeys === null) {
            $key = openssl_pkey_new([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);

            if ($key === false || ! openssl_pkey_export($key, $privateKey)) {
                throw new RuntimeException('Unable to generate the Passport test private key.');
            }

            $details = openssl_pkey_get_details($key);
            if ($details === false || ! is_string($details['key'] ?? null)) {
                throw new RuntimeException('Unable to generate the Passport test public key.');
            }

            self::$passportTestKeys = [
                'private' => $privateKey,
                'public' => $details['key'],
            ];
        }

        $app->make('config')->set([
            'passport.private_key' => self::$passportTestKeys['private'],
            'passport.public_key' => self::$passportTestKeys['public'],
        ]);
    }
}
