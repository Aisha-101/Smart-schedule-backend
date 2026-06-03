<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use App\Notifications\AppointmentConfirmationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AppointmentControllerExtraTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_reschedule_appointment(): void
    {
        $client = User::factory()->create(['role' => 'CLIENT']);
        $specialist = User::factory()->create(['role' => 'SPECIALIST']);

        $appointment = Appointment::create([
            'client_id' => $client->id,
            'specialist_id' => $specialist->id,
            'start_time' => now()->addDays(2)->toDateTimeString(),
            'end_time' => now()->addDays(2)->addMinutes(30)->toDateTimeString(),
            'status' => 'SCHEDULED',
        ]);

        $newStartTime = now()->addDays(3)->toDateTimeString();
        $newEndTime = now()->addDays(3)->addMinutes(30)->toDateTimeString();

        $response = $this->actingAs($client, 'api')->putJson("/api/appointments/{$appointment->id}", [
            'start_time' => $newStartTime,
            'end_time' => $newEndTime,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'start_time' => $newStartTime,
            'end_time' => $newEndTime,
        ]);
    }

    public function test_reschedule_rejects_specialist_overlap(): void
    {
        $client = User::factory()->create(['role' => 'CLIENT']);
        $specialist = User::factory()->create(['role' => 'SPECIALIST']);

        $otherClient = User::factory()->create(['role' => 'CLIENT']);

        $existing = Appointment::create([
            'client_id' => $otherClient->id,
            'specialist_id' => $specialist->id,
            'start_time' => now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
            'end_time' => now()->addDays(2)->setTime(10, 30)->toDateTimeString(),
            'status' => 'SCHEDULED',
        ]);

        $appointment = Appointment::create([
            'client_id' => $client->id,
            'specialist_id' => $specialist->id,
            'start_time' => now()->addDays(2)->setTime(11, 0),
            'end_time' => now()->addDays(2)->setTime(11, 30),
            'status' => 'SCHEDULED',
        ]);

        $response = $this->actingAs($client, 'api')->putJson("/api/appointments/{$appointment->id}", [
            'start_time' => $existing->start_time,
            'end_time' => $existing->end_time,
        ]);

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'This time slot is already taken by the specialist']);
    }

    public function test_client_can_confirm_appointment_one_day_before(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-10 10:00:00'));

        $client = User::factory()->create(['role' => 'CLIENT']);
        $specialist = User::factory()->create(['role' => 'SPECIALIST']);

        $appointment = Appointment::create([
            'client_id' => $client->id,
            'specialist_id' => $specialist->id,
            'start_time' => Carbon::parse('2026-05-11 12:00:00'),
            'end_time' => Carbon::parse('2026-05-11 12:30:00'),
            'status' => 'SCHEDULED',
        ]);

        $response = $this->actingAs($client, 'api')->putJson("/api/appointments/{$appointment->id}/confirm");

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Appointment confirmed succesfully']);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'CONFIRMED',
        ]);

        Carbon::setTestNow();
    }

    public function test_client_can_send_confirmation_email(): void
    {
        Notification::fake();

        $client = User::factory()->create(['role' => 'CLIENT']);
        $specialist = User::factory()->create(['role' => 'SPECIALIST']);

        $appointment = Appointment::create([
            'client_id' => $client->id,
            'specialist_id' => $specialist->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addMinutes(30),
            'status' => 'SCHEDULED',
        ]);

        $response = $this->actingAs($client, 'api')->postJson("/api/appointments/{$appointment->id}/confirm-email");

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Confirmation email sent successfully']);

        Notification::assertSentTo($client, AppointmentConfirmationNotification::class);
    }

    public function test_specialist_can_cancel_appointment_and_email_client(): void
    {
        Mail::fake();

        $client = User::factory()->create(['role' => 'CLIENT']);
        $specialist = User::factory()->create(['role' => 'SPECIALIST']);

        $appointment = Appointment::create([
            'client_id' => $client->id,
            'specialist_id' => $specialist->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addMinutes(30),
            'status' => 'SCHEDULED',
        ]);

        $response = $this->actingAs($specialist, 'api')->putJson("/api/appointments/{$appointment->id}/status", [
            'status' => 'CANCELED',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => 'CANCELED',
        ]);

    }

    public function test_specialist_can_see_client_reliability(): void
    {
        $client = User::factory()->create(['role' => 'CLIENT']);
        $specialist = User::factory()->create(['role' => 'SPECIALIST']);

        Appointment::create([
            'client_id' => $client->id,
            'specialist_id' => $specialist->id,
            'start_time' => now()->subDays(3),
            'end_time' => now()->subDays(3)->addMinutes(30),
            'status' => 'NO_SHOW',
        ]);

        Appointment::create([
            'client_id' => $client->id,
            'specialist_id' => $specialist->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addMinutes(30),
            'status' => 'SCHEDULED',
        ]);

        $response = $this->actingAs($specialist, 'api')->getJson('/api/appointments/my');

        $response->assertOk()
            ->assertJsonFragment(['client_reliability' => 0.5]);
    }
}