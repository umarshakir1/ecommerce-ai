<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class MakeAdmin extends Command
{
    protected $signature   = 'admin:create {email? : Email of existing user to promote, or new admin email}';
    protected $description = 'Promote an existing user to admin, or create a new admin account';

    public function handle(): int
    {
        $email = $this->argument('email') ?? $this->ask('Enter admin email');

        $user = User::where('email', $email)->first();

        if ($user) {
            $user->update(['is_admin' => true]);
            $this->info("✓ User [{$user->name}] has been promoted to admin.");
            return self::SUCCESS;
        }

        $this->warn("No user found with email [{$email}]. Creating a new admin account…");

        $name     = $this->ask('Full name');
        $password = $this->secret('Password (min 8 chars)');

        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');
            return self::FAILURE;
        }

        User::create([
            'name'      => $name,
            'email'     => $email,
            'password'  => Hash::make($password),
            'client_id' => \Illuminate\Support\Str::uuid(),
            'api_key'   => bin2hex(random_bytes(32)),
            'is_admin'  => true,
        ]);

        $this->info("✓ Admin account created for [{$email}].");
        return self::SUCCESS;
    }
}
