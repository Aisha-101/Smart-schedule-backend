<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\SpecialistAvailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommendation_rejects_past_date(): void
    {
        $client = User::factory()->create(['role' => 'CLIENT']);
        $specialist = User::factory()->create(['role' => 'SPECIALIST']);

        $response = $this->actingAs($client, 'api')->getJson('/api/recommendations?specialist_id='.$specialist->id.'&date='.now()->subDay()->toDateString());

        $response->assertStatus(422)->assertJsonFragment(['message' => 'Cannot recommend times in the past.']);
    }

    public function test_recommendation_returns_slots_and_warnings_for_high_load(): void
    {
        $client = User::factory()->create(['role' => 'CLIENT']);
        $specialist = User::factory()->create(['role' => 'SPECIALIST']);

        $service = Service::create([
            'name' => 'Cut', 'duration' => 30, 'price' => 10, 'specialist_id' => $specialist->id,
        ]);

        $date = now()->addDays(2)->toDateString();

        SpecialistAvailability::create([
            'specialist_id' => $specialist->id,
            'date' => $date,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        for ($i = 0; $i < 8; $i++) {
            Appointment::create([
                'client_id' => $client->id,
                'specialist_id' => $specialist->id,
                'start_time' => now()->addDays(2)->setTime(13, 0)->addMinutes($i),
                'end_time' => now()->addDays(2)->setTime(13, 30)->addMinutes($i),
                'status' => 'SCHEDULED',
            ]);
        }

        $response = $this->actingAs($client, 'api')->getJson('/api/recommendations?specialist_id='.$specialist->id.'&date='.$date.'&service_ids[]='.$service->id);

        $response->assertOk()
            ->assertJsonStructure(['slots', 'warnings', 'alternative_day_slots'])
            ->assertJsonFragment(['Selected specialist has high load on this day.']);
    }

    public function test_recommendation_rejects_service_from_another_specialist(): void
    {
        $client = User::factory()->create(['role' => 'CLIENT']);
        $specialistA = User::factory()->create(['role' => 'SPECIALIST']);
        $specialistB = User::factory()->create(['role' => 'SPECIALIST']);

        $service = Service::create([
            'name' => 'Massage', 'duration' => 45, 'price' => 20, 'specialist_id' => $specialistB->id,
        ]);

        $date = now()->addDay()->toDateString();
        SpecialistAvailability::create([
            'specialist_id' => $specialistA->id,
            'date' => $date,
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]);

        $response = $this->actingAs($client, 'api')->getJson('/api/recommendations?specialist_id='.$specialistA->id.'&date='.$date.'&service_ids[]='.$service->id);

        $response->assertOk()->assertJsonFragment(['message' => 'All selected services must belong to the selected specialist.']);
    }
}