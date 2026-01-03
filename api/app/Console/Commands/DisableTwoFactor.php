<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DisableTwoFactor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:disable-two-factor {user_email} {reason}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Disable Two-Factor Authentication for a User';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if (!$this->confirm('Are you sure you want to disable two-factor authentication for user ' . $this->argument('user_email') . '?', true)) {
            $this->info('Operation cancelled.');
            return Command::SUCCESS;
        }

        $user = User::whereEmail($this->argument('user_email'))->first();
        if (!$user) {
            $this->error("User not found.");
            return Command::FAILURE;
        }

        if ($user->admin) {
            $this->error('You cannot disable 2FA for an admin.');
            return Command::FAILURE;
        }

        if (!$user->hasTwoFactorEnabled()) {
            $this->error("Two-factor authentication is not enabled.");
            return Command::FAILURE;
        }

        // Disable 2FA
        $user->disableTwoFactorAuth();

        Log::channel('slack_admin')->warning('Via Command: Disable Two-Factor Authentication ', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'reason' => $this->argument('reason')
        ]);

        $this->info("Two-factor authentication has been disabled successfully.");
    }
}
