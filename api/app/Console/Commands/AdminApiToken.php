<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Service\AdminApi\Operations;
use Illuminate\Console\Command;

class AdminApiToken extends Command
{
    protected $signature = 'admin-api:token {owner : Moderator email} {--name=Bureau} {--ability=* : Explicit scope, repeat for each operation} {--days=30} {--revoke= : Token ID to revoke}';
    protected $description = 'Issue or revoke a scoped admin API token from a trusted operator terminal';

    public function handle(): int
    {
        $owner = User::where('email', $this->argument('owner'))->first();
        if (!$owner) {
            $this->error('The owner must be an active moderator.');
            return self::FAILURE;
        }
        if ($id = $this->option('revoke')) {
            $token = $owner->tokens()->find($id);
            if (!$token || !in_array(Operations::TOKEN_MARKER, $token->abilities, true)) {
                $this->error('Admin token not found for this owner.');
                return self::FAILURE;
            }
            $token->delete();
            $this->info('Token revoked.');
            return self::SUCCESS;
        }
        if (!$owner->moderator || $owner->is_blocked) {
            $this->error('The owner must be an active moderator.');
            return self::FAILURE;
        }
        $abilities = array_values(array_unique($this->option('ability')));
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT);
        if (!$abilities || array_diff($abilities, Operations::abilities()) || !$days || $days < 1 || $days > 90) {
            $this->error('Select explicit admin scopes and an expiration between 1 and 90 days.');
            $this->line(implode("\n", Operations::abilities()));
            return self::FAILURE;
        }
        $token = $owner->createToken($this->option('name'), [Operations::TOKEN_MARKER, ...$abilities], now()->addDays($days));
        $this->info('Token ID: '.$token->accessToken->id.'. Store this secret in the integration vault; it is shown once.');
        $this->line($token->plainTextToken);
        return self::SUCCESS;
    }
}
