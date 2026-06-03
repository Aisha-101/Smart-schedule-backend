<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\Specialist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_belongs_to_specialist(): void
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
}