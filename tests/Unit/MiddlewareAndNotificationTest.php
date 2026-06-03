<?php

namespace Tests\Unit;

use App\Http\Middleware\HandleCors;
use App\Http\Middleware\RoleMiddleware;
use App\Models\Appointment;
use App\Models\User;
use App\Notifications\AppointmentConfirmationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class MiddlewareAndNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_cors_options_request_returns_cors_headers(): void
    {
        $middleware = new HandleCors();
        $request = Request::create('/api/services', 'OPTIONS');

        $response = $middleware->handle($request, fn () => new Response());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('http://localhost:5173', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_role_middleware_rejects_unauthorized_and_forbidden(): void
    {
        $middleware = new RoleMiddleware();

        $guestResponse = $middleware->handle(Request::create('/api/admin/users', 'GET'), fn () => response()->json(['ok' => true]), 'ADMIN');
        $this->assertSame(401, $guestResponse->getStatusCode());

        $client = User::factory()->create(['role' => 'CLIENT']);
        $this->actingAs($client, 'api');

        $forbiddenResponse = $middleware->handle(Request::create('/api/admin/users', 'GET'), fn () => response()->json(['ok' => true]), 'ADMIN');
        $this->assertSame(403, $forbiddenResponse->getStatusCode());
    }

    public function test_appointment_confirmation_notification_mail_content(): void
    {
        $user = User::factory()->create(['role' => 'CLIENT']);
        $specialist = User::factory()->create(['role' => 'SPECIALIST']);
        $appointment = Appointment::create([
            'client_id' => $user->id,
            'specialist_id' => $specialist->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addMinutes(30),
            'status' => 'SCHEDULED',
        ]);

        $notification = new AppointmentConfirmationNotification($appointment, 'http://example.com/confirm');
        $mail = $notification->toMail($user);

        $this->assertSame(['mail'], $notification->via($user));
        $this->assertSame('Confirm your appointment', $mail->subject);
    }
}