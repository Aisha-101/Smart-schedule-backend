<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthAndAppointmentExtendedTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_profile_update_and_logout(): void
    {
        Notification::fake();

        $this->postJson('/api/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertOk();

        $user = User::where('email', 'new@example.com')->firstOrFail();
        $user->forceFill(['email_verified_at' => now()])->save();

        $login = $this->postJson('/api/login', [
            'email' => 'new@example.com',
            'password' => 'password123',
        ])->assertOk();

        $token = $login->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/me', ['name' => 'Updated User'])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Updated User']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/logout')
            ->assertOk();
    }

    public function test_appointment_update_confirm_and_email_confirmation_paths(): void
    {
        Notification::fake();

        $client = User::factory()->create(['role' => 'CLIENT']);
        $specialist = User::factory()->create(['role' => 'SPECIALIST']);

        $service = Service::create([
            'name' => 'Hair',
            'duration' => 30,
            'price' => 25,
            'specialist_id' => $specialist->id,
        ]);

        $appointment = Appointment::create([
            'client_id' => $client->id,
            'specialist_id' => $specialist->id,
            'start_time' => now()->addDay()->setTime(10, 0, 0)->toDateTimeString(),
            'end_time' => now()->addDay()->setTime(10, 30, 0)->toDateTimeString(),
            'status' => 'SCHEDULED',
        ]);

        $appointment->services()->attach([$service->id]);

        $this->actingAs($client, 'api')->putJson('/api/appointments/'.$appointment->id, [
            'start_time' => now()->addDay()->setTime(11, 0, 0)->toDateTimeString(),
            'end_time' => now()->addDay()->setTime(11, 30, 0)->toDateTimeString(),
        ])->assertOk();

        $appointment->refresh();

        $this->actingAs($client, 'api')
            ->postJson('/api/appointments/'.$appointment->id.'/confirm-email')
            ->assertOk();

        $hash = sha1(
            $appointment->id . '|' .
            $client->email . '|' .
            $appointment->start_time
        );

        $this->getJson('/api/appointments/'.$appointment->id.'/confirm-email/'.$hash)
            ->assertOk()
            ->assertJson([
                'message' => 'Appointment confirmed successfully.',
            ]);

        $appointment->refresh();

        $this->assertEquals('CONFIRMED', $appointment->status);
    }

    public function test_command_does_not_send_email_if_already_sent(): void
    {
        Notification::fake();

        $client = User::factory()->create(['role' => 'CLIENT']);
        $specialist = User::factory()->create(['role' => 'SPECIALIST']);

        Appointment::create([
            'client_id' => $client->id,
            'specialist_id' => $specialist->id,
            'start_time' => now()->addDay()->setTime(10, 0, 0),
            'end_time' => now()->addDay()->setTime(10, 30, 0),
            'status' => 'SCHEDULED',
            'confirmation_email_sent_at' => now(),
        ]);

        $this->artisan('appointments:send-confirmation-emails')
            ->expectsOutput('Confirmation emails sent: 0')
            ->assertExitCode(0);

        Notification::assertNothingSent();
    }
    public function test_command_does_not_send_email_for_canceled_appointments(): void
    {
        Notification::fake();

        $client = User::factory()->create(['role' => 'CLIENT']);
        $specialist = User::factory()->create(['role' => 'SPECIALIST']);

        Appointment::create([
            'client_id' => $client->id,
            'specialist_id' => $specialist->id,
            'start_time' => now()->addDay()->setTime(10, 0, 0),
            'end_time' => now()->addDay()->setTime(10, 30, 0),
            'status' => 'CANCELED',
            'confirmation_email_sent_at' => null,
        ]);

        $this->artisan('appointments:send-confirmation-emails')
            ->expectsOutput('Confirmation emails sent: 0')
            ->assertExitCode(0);

        Notification::assertNothingSent();
    }
    public function test_email_confirmation_returns_ok_if_already_confirmed(): void
    {
        $client = User::factory()->create(['role' => 'CLIENT']);
        $specialist = User::factory()->create(['role' => 'SPECIALIST']);

        $appointment = Appointment::create([
            'client_id' => $client->id,
            'specialist_id' => $specialist->id,
            'start_time' => now()->addDay()->setTime(10, 0, 0)->toDateTimeString(),
            'end_time' => now()->addDay()->setTime(10, 30, 0)->toDateTimeString(),
            'status' => 'CONFIRMED',
        ]);

        $appointment->refresh();

        $hash = sha1(
            $appointment->id . '|' .
            $client->email . '|' .
            $appointment->start_time
        );

        $this->getJson('/api/appointments/'.$appointment->id.'/confirm-email/'.$hash)
            ->assertOk()
            ->assertJson([
                'message' => 'Appointment is already confirmed.',
            ]);
    }
}