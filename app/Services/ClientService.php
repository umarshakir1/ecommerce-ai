<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

class ClientService
{
    public function generateClientId(): string
    {
        return (string) Str::uuid();
    }

    public function generateApiKey(): string
    {
        return Str::random(64);
    }

    public function getClientByApiKey(string $apiKey): ?User
    {
        return User::where('api_key', $apiKey)->first();
    }

    public function regenerateApiKey(User $user): string
    {
        $newKey = $this->generateApiKey();
        $user->update(['api_key' => $newKey]);
        return $newKey;
    }
}
