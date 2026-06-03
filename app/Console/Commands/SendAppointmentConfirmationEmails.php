<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Notifications\AppointmentConfirmationNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendAppointmentConfirmationEmails extends Command
{
    protected $signature = 'appointments:send-confirmation-emails';

    protected $description = 'Send appointment confirmation emails one day before appointment';

    public function handle(): int
    {
        $tomorrow = Carbon::tomorrow();

        $appointments = Appointment::with('client')
            ->where('status', 'SCHEDULED')
            ->whereDate('start_time', $tomorrow)
            ->whereNull('confirmation_email_sent_at')
            ->get();

        foreach ($appointments as $appointment) {
            if (! $appointment->client || ! $appointment->client->email) {
                continue;
            }

            $hash = sha1(
                $appointment->id . '|' .
                $appointment->client->email . '|' .
                $appointment->start_time
            );

            $frontendUrl = rtrim(config('app.url'), '/');

            $url = $frontendUrl .
                '/confirm-appointment?id=' .
                $appointment->id .
                '&hash=' .
                $hash;

            $appointment->client->notify(
                new AppointmentConfirmationNotification($appointment, $url)
            );

            $appointment->update([
                'confirmation_email_sent_at' => now(),
            ]);
        }

        $this->info('Confirmation emails sent: ' . $appointments->count());

        return self::SUCCESS;
    }
}