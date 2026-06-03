<?php

namespace Tests\Feature;

use App\Models\Specialist;
use App\Models\SpecialistAvailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSpecialistAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_show_and_delete_users_and_sync_specialists(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $specialistUser = User::factory()->create(['role' => 'SPECIALIST']);

        $this->actingAs($admin, 'api')->getJson('/api/admin/users/'.$specialistUser->id)->assertOk();

        $sync = $this->actingAs($admin, 'api')->postJson('/api/specialists/sync-from-users')->assertOk();
        $sync->assertJsonStructure(['message', 'count']);

        $this->assertDatabaseHas('specialists', ['user_id' => $specialistUser->id]);

        $toDelete = User::factory()->create(['role' => 'CLIENT']);
        $this->actingAs($admin, 'api')->deleteJson('/api/admin/users/'.$toDelete->id)->assertOk();
    }

    public function test_specialist_availability_conflict_and_success_paths(): void
    {
        $specialist = User::factory()->create(['role' => 'SPECIALIST']);

        $date = now()->addDays(3)->toDateString();

        $this->actingAs($specialist, 'api')->postJson('/api/specialists/'.$specialist->id.'/schedule', [
            'date' => $date,
            'start_time' => '09:00',
            'end_time' => '10:00',
        ])->assertCreated();

        $this->actingAs($specialist, 'api')->postJson('/api/specialists/'.$specialist->id.'/schedule', [
            'date' => $date,
            'start_time' => '09:30',
            'end_time' => '10:30',
        ])->assertStatus(422);

        $this->actingAs($specialist, 'api')->getJson('/api/specialists/'.$specialist->id.'/schedule')
            ->assertOk();
    }

    public function test_specialist_crud_paths(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $specialistUser = User::factory()->create(['role' => 'SPECIALIST']);

        $created = $this->actingAs($admin, 'api')->postJson('/api/specialists', [
            'user_id' => $specialistUser->id,
            'specialization' => 'Dermatology',
            'workload_factor' => 1.1,
        ])->assertCreated();

        $specialistId = $created->json('id');

        $this->actingAs($admin, 'api')->putJson('/api/specialists/'.$specialistId, [
            'specialization' => 'General',
            'workload_factor' => 1.2,
        ])->assertOk();

        $this->actingAs($admin, 'api')->deleteJson('/api/specialists/'.$specialistId)->assertOk();
    }
}