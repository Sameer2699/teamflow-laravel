<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\PersonalAccessToken;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'newuser@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Registration successful.')
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'token',
            ]);
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'Password@123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'login@example.com',
            'password' => 'Password@123',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'token',
            ]);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'Password@123',
        ]);

        $this->postJson('/api/login', [
            'email' => 'login@example.com',
            'password' => 'WrongPassword',
        ])->assertUnauthorized();
    }

    public function test_authenticated_user_can_view_profile(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', $user->email);
    }

public function test_user_can_logout_and_token_is_revoked(): void
{
    $user = User::factory()->create();

    $token = $user->createToken('test-token')->plainTextToken;

    // Logout using the generated token
    $this->withToken($token)
        ->postJson('/api/logout')
        ->assertOk()
        ->assertJsonPath('message', 'Logout successful.');

    // Verify that the token has actually been deleted
    $this->assertNull(
        PersonalAccessToken::findToken($token)
    );

    // Clear cached authentication guards between requests
    $this->app['auth']->forgetGuards();

    // The revoked token must no longer authenticate
    $this->withToken($token)
        ->getJson('/api/me')
        ->assertUnauthorized();
}


    public function test_unauthenticated_user_cannot_view_profile(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }
}
