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
    protected $signature = 'user:disable-two-factor {user_email} {reason}
                            {--force : Skip confirmation prompt}
                            {--allow-admin : Allow disabling 2FA for admin users (requires confirmation)}';

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
        $user = User::whereEmail(strtolower($this->argument('user_email')))->first();
        if (!$user) {
            $this->error("User not found.");
            return Command::FAILURE;
        }

        if ($user->admin) {
            if (!$this->option('allow-admin')) {
                $this->error('You cannot disable 2FA for an admin. Use --allow-admin flag if intended.');
                return Command::FAILURE;
            }

            if ($this->option('force')) {
                $this->error('Cannot use --force with --allow-admin for safety reasons.');
                return Command::FAILURE;
            }

            if (!$this->confirm('WARNING: This user is an admin. Are you sure you want to disable their 2FA?', false)) {
                $this->info('Operation cancelled.');
                return Command::SUCCESS;
            }
        }

        if (!$user->hasTwoFactorEnabled()) {
            $this->error("Two-factor authentication is not enabled.");
            return Command::FAILURE;
        }

        if (!$this->option('force') && !$this->confirm('Are you sure you want to disable two-factor authentication for user ' . $this->argument('user_email') . '?', true)) {
            $this->info('Operation cancelled.');
            return Command::SUCCESS;
        }

        $user->disableTwoFactorAuth();

        Log::channel('slack_admin')->warning('Via Command: Disable Two-Factor Authentication ', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'reason' => $this->argument('reason'),
            'admin_override' => $this->option('allow-admin'),
        ]);

        $this->info("Two-factor authentication has been disabled successfully.");

        return Command::SUCCESS;
    }
}
