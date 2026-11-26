<?php

namespace App\Service\Forms\Analytics\IpLocation;

use Illuminate\Http\Client\Factory;

/** Provider URLs contain visitor IPs, so this client runs without global HTTP instrumentation. */
final class IpLocationHttpClient extends Factory
{
}
