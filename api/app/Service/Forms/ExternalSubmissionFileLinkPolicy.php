<?php

namespace App\Service\Forms;

use App\Models\Workspace;
use Carbon\Carbon;

class ExternalSubmissionFileLinkPolicy
{
    public const DEFAULT_EXPIRATION_HOURS = 24;

    public const ALLOWED_EXPIRATION_HOURS = [
        24,
        72,
        168,
        336,
        720,
    ];

    public function expirationHours(Workspace $workspace): int
    {
        $expirationHours = data_get($workspace->settings ?? [], 'external_file_links.expires_in_hours');

        return in_array($expirationHours, self::ALLOWED_EXPIRATION_HOURS, true)
            ? $expirationHours
            : self::DEFAULT_EXPIRATION_HOURS;
    }

    public function expiresAt(Workspace $workspace): Carbon
    {
        return now()->addHours($this->expirationHours($workspace));
    }
}
