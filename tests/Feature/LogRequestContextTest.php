<?php

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

it('adds structured request context to logs', function (): void {
    $user = User::factory()->create();

    Log::spy();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertHeader('X-Request-Id');

    Log::shouldHaveReceived('withContext')
        ->once()
        ->withArgs(function (array $context) use ($user): bool {
            return ($context['user_id'] ?? null) === $user->id
                && ($context['route'] ?? null) === 'dashboard'
                && is_string($context['request_id'] ?? null)
                && Str::isUuid($context['request_id']);
        });
});
