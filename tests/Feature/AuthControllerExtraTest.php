<?php

namespace Tests\Feature;

use App\Models\Specialist;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthControllerExtraTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_requires_valid_email(): void
    {
        $this->postJson('/api/forgot-password', [])
            ->assertStatus(422);
    }

    public function test_forgot_password_sends_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'role' => 'CLIENT',
            'email' => 'client@example.com',
        ]);

        $response = $this->postJson('/api/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Password reset link sent to your email']);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_with_invalid_token_returns_error(): void
    {
        $user = User::factory()->create([
            'role' => 'CLIENT',
            'email' => 'client@example.com',
        ]);

        $response = $this->postJson('/api/reset-password', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ]);

        $response->assertStatus(400);
    }

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create([
            'role' => 'CLIENT',
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $response = $this->actingAs($user, 'api')->putJson('/api/me', [
            'name' => 'New Name',
            'email' => 'new@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertOk()
            ->assertJsonFragment([
                'name' => 'New Name',
                'email' => 'new@example.com',
            ]);

        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    public function test_specialist_can_update_specialization_in_profile(): void
    {
        $user = User::factory()->create([
            'role' => 'SPECIALIST',
            'name' => 'Jane',
            'email' => 'jane@example.com',
        ]);

        Specialist::create([
            'user_id' => $user->id,
            'specialization' => 'General',
            'workload_factor' => 1,
        ]);

        $response = $this->actingAs($user, 'api')->putJson('/api/me', [
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'specialization' => 'Hair stylist',
        ]);

        $response->assertOk()
            ->assertJsonPath('specialist.specialization', 'Hair stylist');

        $this->assertDatabaseHas('specialists', [
            'user_id' => $user->id,
            'specialization' => 'Hair stylist',
        ]);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create([
            'role' => 'CLIENT',
            'email' => 'client@example.com',
            'password' => bcrypt('password123'),
            'email_verified_at' => now(),
        ]);

        $login = $this->postJson('/api/login', [
            'email' => 'client@example.com',
            'password' => 'password123',
        ]);

        $token = $login->json('token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/logout');

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Logged out successfully']);
    }
}