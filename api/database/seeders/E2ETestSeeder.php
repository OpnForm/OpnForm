<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class E2ETestSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('user_workspace')->truncate();
        Workspace::query()->delete();
        User::query()->delete();

        $user = User::query()->create([
            'name' => 'E2E User',
            'email' => 'e2e@example.test',
            'password' => Hash::make('Abcd@1234'),
            'hear_about_us' => 'e2e',
            'email_verified_at' => now(),
        ]);

        $workspace = Workspace::query()->create([
            'name' => 'E2E Workspace',
            'icon' => '🧪',
        ]);

        $user->workspaces()->sync([
            $workspace->id => ['role' => User::ROLE_ADMIN],
        ]);
    }
}
