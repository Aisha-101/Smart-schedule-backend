<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_and_api_pages_are_reachable(): void
    {
        $client = User::factory()->create(['role' => 'CLIENT']);
        $specialist = User::factory()->create(['role' => 'SPECIALIST']);

        $service = Service::create([
            'name' => 'Integration Service',
            'duration' => 30,
            'price' => 20,
            'specialist_id' => $specialist->id,
        ]);

        $appointment = Appointment::create([
            'client_id' => $client->id,
            'specialist_id' => $specialist->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addMinutes(30),
            'status' => 'SCHEDULED',
        ]);

        $this->get('/')->assertOk();

        $this->postJson('/api/register', [])->assertStatus(422);
        $this->postJson('/api/login', [])->assertStatus(401);
        $this->postJson('/api/forgot-password', [])->assertStatus(422);
        $this->postJson('/api/reset-password', [])->assertStatus(422);
        $this->getJson('/api/reset-password/test-token')->assertOk();
        $this->postJson('/api/email/resend', [])->assertStatus(404);
        $this->getJson('/api/services')->assertOk();
        $this->getJson('/api/specialists')->assertOk();
        $this->getJson("/api/appointments/{$appointment->id}/confirm-email/bad-hash")
            ->assertStatus(422)
            ->assertJson([
                'message' => 'Invalid confirmation link.',
            ]);
        $this->getJson('/api/email/verify/999/invalid')->assertStatus(404);

        $this->getJson('/api/appointments')->assertStatus(401);
        $this->getJson('/api/appointments/my')->assertStatus(401);
        $this->putJson('/api/me', [])->assertStatus(401);
        $this->getJson('/api/recommendations')->assertStatus(401);

        $this->actingAs($specialist, 'api')->getJson('/api/my-services')->assertOk();
        $this->actingAs($specialist, 'api')->postJson('/api/services', [])->assertStatus(422);
        $this->actingAs($specialist, 'api')->putJson('/api/appointments/999/status', [])->assertStatus(422);
        $this->actingAs($specialist, 'api')->getJson("/api/specialists/{$specialist->id}/schedule")->assertOk();
        $this->actingAs($specialist, 'api')->postJson("/api/specialists/{$specialist->id}/schedule", [])->assertStatus(422);

        $this->actingAs($client, 'api')->postJson('/api/appointments', [
            'specialist_id' => $specialist->id,
            'start_time' => now()->addDays(2)->toDateTimeString(),
            'end_time' => now()->addDays(2)->addMinutes(30)->toDateTimeString(),
            'services' => [$service->id],
        ])->assertStatus(201);
        $this->actingAs($client, 'api')->putJson('/api/appointments/999', [])->assertStatus(404);
        $this->actingAs($client, 'api')->deleteJson('/api/appointments/999')->assertStatus(404);
        $this->actingAs($client, 'api')->putJson('/api/appointments/999/confirm')->assertStatus(404);
        $this->actingAs($client, 'api')->postJson('/api/appointments/999/confirm-email')->assertStatus(404);
        $this->actingAs($client, 'api')->getJson("/api/specialists/{$specialist->id}/services")->assertOk();

        $admin = User::factory()->create(['role' => 'ADMIN']);

        $this->actingAs($admin, 'api')->putJson("/api/services/{$service->id}", [
            'name' => 'Updated Integration Service',
            'duration' => 30,
            'price' => 25,
        ])->assertOk();
        $this->actingAs($admin, 'api')->getJson('/api/statistics')->assertOk();
        $this->actingAs($admin, 'api')->getJson('/api/admin/users')->assertOk();
    }
}