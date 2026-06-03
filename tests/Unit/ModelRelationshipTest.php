<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\Specialist;
use App\Models\SpecialistAvailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_belongs_to_specialist_user(): void
    {
        $specialist = User::factory()->create(['role' => 'SPECIALIST']);

        $service = Service::create([
            'name' => 'Haircut',
            'duration' => 30,
            'price' => 15,
            'specialist_id' => $specialist->id,
        ]);

        $this->assertSame($specialist->id, $service->specialist->id);
    }

    public function test_specialist_belongs_to_user(): void
    {
        $user = User::factory()->create(['role' => 'SPECIALIST']);

        $specialist = Specialist::create([
            'user_id' => $user->id,
            'specialization' => 'Hair stylist',
            'workload_factor' => 1,
        ]);

        $this->assertSame($user->id, $specialist->user->id);
    }

    public function test_specialist_has_services(): void
    {
        $user = User::factory()->create(['role' => 'SPECIALIST']);

        $specialist = Specialist::create([
            'user_id' => $user->id,
            'specialization' => 'Hair stylist',
            'workload_factor' => 1,
        ]);

        Service::create([
            'name' => 'Trim',
            'duration' => 30,
            'price' => 10,
            'specialist_id' => $specialist->id,
        ]);

        $this->assertCount(1, $specialist->services);
    }

    public function test_appointment_belongs_to_client_and_specialist(): void
    {
        $client = User::factory()->create(['role' => 'CLIENT']);
        $specialist = User::factory()->create(['role' => 'SPECIALIST']);

        $appointment = Appointment::create([
            'client_id' => $client->id,
            'specialist_id' => $specialist->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addMinutes(30),
            'status' => 'SCHEDULED',
        ]);

        $this->assertSame($client->id, $appointment->client->id);
        $this->assertSame($specialist->id, $appointment->specialist->id);
    }

    public function test_specialist_availability_belongs_to_specialist(): void
    {
        $user = User::factory()->create(['role' => 'SPECIALIST']);

        $specialist = Specialist::create([
            'user_id' => $user->id,
            'specialization' => 'Hair stylist',
            'workload_factor' => 1,
        ]);

        $availability = SpecialistAvailability::create([
            'specialist_id' => $specialist->id,
            'date' => now()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $this->assertSame($specialist->id, $availability->specialist->id);
    }
}