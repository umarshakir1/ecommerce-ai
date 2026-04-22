<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public const CLIENT_ID = '00000000-0000-0000-0000-000000000001';

    public function run(): void
    {
        $apiKey = env('DEMO_API_KEY', 'ShopAIDemoKey2026xK9mN2pQr8vXjL5wA3hE9tYcF6bDsG4iZ0uV1nMoR7qTkP2');

        $user = User::firstOrCreate(
            ['email' => 'demo@shopai.test'],
            [
                'name'       => 'ShopAI Demo',
                'password'   => Hash::make('demo-password-not-for-login'),
                'client_id'  => self::CLIENT_ID,
                'api_key'    => $apiKey,
                'is_active'  => true,
            ]
        );

        if ($user->wasRecentlyCreated === false) {
            $user->update(['api_key' => $apiKey, 'client_id' => self::CLIENT_ID, 'is_active' => true]);
        }

        $updated = Product::where('client_id', '')->update(['client_id' => self::CLIENT_ID]);

        $this->command->info("Demo user ready  →  API key: {$apiKey}");
        $this->command->info("Products assigned to demo client: {$updated}");
    }
}
