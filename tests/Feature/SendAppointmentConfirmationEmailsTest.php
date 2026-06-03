<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\User;
use App\Notifications\AppointmentConfirmationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendAppointmentConfirmationEmailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_sends_confirmation_email_for_tomorrow_appointments(): void
    {
        Notification::fake();

        $client = User::factory()->create([
            'role' => 'CLIENT',
            'email' => 'client@test.lt',
        ]);

        $specialist = User::factory()->create([
            'role' => 'SPECIALIST',
        ]);

        $appointment = Appointment::create([
            'client_id' => $client->id,
            'specialist_id' => $specialist->id,
            'start_time' => now()->addDay()->setTime(10, 0, 0),
            'end_time' => now()->addDay()->setTime(10, 30, 0),
            'status' => 'SCHEDULED',
            'confirmation_email_sent_at' => null,
        ]);

        $this->artisan('appointments:send-confirmation-emails')
            ->expectsOutput('Confirmation emails sent: 1')
            ->assertExitCode(0);

        Notification::assertSentTo(
            $client,
            AppointmentConfirmationNotification::class
        );

        $appointment->refresh();

        $this->assertNotNull($appointment->confirmation_email_sent_at);
    }

    public function test_command_does_not_send_email_for_not_tomorrow_appointments(): void
    {
        Notification::fake();

        $client = User::factory()->create([
            'role' => 'CLIENT',
        ]);

        $specialist = User::factory()->create([
            'role' => 'SPECIALIST',
        ]);

        Appointment::create([
            'client_id' => $client->id,
            'specialist_id' => $specialist->id,
            'start_time' => now()->addDays(3)->setTime(10, 0, 0),
            'end_time' => now()->addDays(3)->setTime(10, 30, 0),
            'status' => 'SCHEDULED',
            'confirmation_email_sent_at' => null,
        ]);

        $this->artisan('appointments:send-confirmation-emails')
            ->expectsOutput('Confirmation emails sent: 0')
            ->assertExitCode(0);

        Notification::assertNothingSent();
    }
}