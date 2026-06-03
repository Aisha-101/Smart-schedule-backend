<?php

namespace App\Http\Controllers;
use App\Models\Appointment;
use App\Notifications\AppointmentConfirmationNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class AppointmentController extends Controller
{
    public function index()
    {
        return Appointment::with(['services','client','specialist'])->get();
    }
    public function store(Request $request)
    {
        $request->validate([
            'specialist_id' => 'required|exists:users,id',
            'services' => 'required|array',
            'services.*' => 'exists:services,id',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time'
        ]);

        $clientId = auth()->id();

        $serviceCount = \App\Models\Service::whereIn('id', $request->services)
            ->where('specialist_id', $request->specialist_id)
            ->count();

        if ($serviceCount !== count(array_unique($request->services))) {
            return response()->json([
                'message' => 'All selected services must belong to the selected specialist'
            ], 422);
        }

        $overlap = Appointment::where('specialist_id', $request->specialist_id)
            ->where('status', '!=', 'CANCELED')
            ->where(function ($query) use ($request){
                $query->where('start_time', '<', $request->end_time)
                    ->where('end_time', '>', $request->start_time);
            })
            ->exists();

        if($overlap){
            return response()->json([
                'message' => 'Time slot is already taken'
            ], 400);
        }

        $appointment = Appointment::create([
            'client_id' => $clientId,
            'specialist_id' => $request->specialist_id,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'status' => 'SCHEDULED'
        ]);
        $appointment->services()->attach($request->services);

        return response()->json($appointment->load('services'), 201);
    }

    public function update(Request $request, $id)
    {
        $appointment = Appointment::findOrFail($id);

        $request->validate([
            'start_time' => 'sometimes|required|date',
            'end_time' => 'sometimes|required|date|after:start_time',
            'status' => 'sometimes|required|in:SCHEDULED,CONFIRMED,COMPLETED,CANCELED,NO_SHOW,LATE',
            'services' => 'sometimes|array',
            'services.*' => 'exists:services,id'
        ]);

        if ($request->has('start_time') && $request->has('end_time')) {

        $specialistOverlap = Appointment::where('specialist_id', $appointment->specialist_id)
            ->where('id', '!=', $appointment->id)
            ->where('status', '!=', 'CANCELED')
            ->where(function ($query) use ($request) {
                $query->where('start_time', '<', $request->end_time)
                    ->where('end_time', '>', $request->start_time);
            })
            ->exists();

        if ($specialistOverlap) {
            return response()->json([
                'message' => 'This time slot is already taken by the specialist'
            ], 400);
        }

        $clientOverlap = Appointment::where('client_id', $appointment->client_id)
            ->where('id', '!=', $appointment->id)
            ->where('status', '!=', 'CANCELED')
            ->where(function ($query) use ($request) {
                $query->where('start_time', '<', $request->end_time)
                    ->where('end_time', '>', $request->start_time);
            })
            ->exists();

        if ($clientOverlap) {
            return response()->json([
                'message' => 'Client already has another appointment at this time'
            ], 400);
        }

        $appointment->update([
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
        ]);

        if ($request->has('status')) {
            $appointment->update([
                'status' => $request->status,
            ]);
        }

        if ($request->has('services')) {
            $appointment->services()->sync($request->services);
        }

        return $appointment->load(['services','client','specialist']);
    }

    if ($request->has('status')) {
        $appointment->update([
            'status' => $request->status,
        ]);
    }

    if ($request->has('services')) {
        $appointment->services()->sync($request->services);
    }


    }

    //Cancel Appointment
    public function destroy($id)
    {
        $appointment = Appointment::findOrFail($id);

        $user = auth()->user();

        if ($user->role !== 'CLIENT' || (int) $appointment->client_id !== (int) $user->id) {
            return response()->json([
                'message' => 'You can only cancel your own appointments.'
            ], 403);
        }

        if (! in_array($appointment->status, ['SCHEDULED', 'CONFIRMED'])) {
            return response()->json([
                'message' => 'Only scheduled or confirmed appointments can be canceled.'
            ], 422);
        }

        $appointmentStart = Carbon::parse($appointment->start_time);

        if ($appointment->status === 'CONFIRMED') {
        $appointmentStart = Carbon::parse($appointment->start_time);

        if (now()->diffInHours($appointmentStart, false) < 24) {
                return response()->json([
                    'message' => 'Confirmed appointments can only be canceled at least 24 hours before the start time.'
                ], 422);
            }
        }

        $appointment->update([
            'status' => 'CANCELED'
        ]);

        return response()->json([
            'message' => 'Appointment canceled successfully'
        ]);
    }
    public function my()
    {
        $user = auth()->user();

        if($user->role === 'CLIENT'){
            return Appointment::with([
                'services',
                'specialist'
            ])
            ->where('client_id', $user->id)
            ->get();
        }

        if ($user->role === 'SPECIALIST') {
            $appointments = Appointment::with([
                'services',
                'client'
            ])
            ->where('specialist_id', $user->id)
            ->get();

            return $appointments->map(function ($appointment) {
                $appointment->client_reliability = $this->calculateClientReliability($appointment->client_id);

                return $appointment;
            });
        }

        return response()->json([]);
    }

    private function calculateClientReliability($clientId)
    {
        $appointments = Appointment::where('client_id', $clientId)->get();

        if ($appointments->count() === 0) {
            return 0.70;
        }

        $noShows = $appointments->where('status', 'NO_SHOW')->count();

        return round(1 - ($noShows / $appointments->count()), 2);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:SCHEDULED,CONFIRMED,COMPLETED,CANCELED,NO_SHOW,LATE',
            'delay_minutes' => 'nullable|integer|min:0',
            'cancellation_reason' => 'nullable|string|max:1000',
        ]);

        $user = auth()->user();

        $appointment = Appointment::with(['client', 'services'])
            ->findOrFail($id);

        if ($user->role === 'SPECIALIST' && (int) $appointment->specialist_id !== (int) $user->id) {
            return response()->json([
                'message' => 'You can only update your own appointments.'
            ], 403);
        }

        if ($request->status === 'CANCELED') {
            if (! in_array($appointment->status, ['SCHEDULED', 'CONFIRMED'])) {
                return response()->json([
                    'message' => 'Only scheduled or confirmed appointments can be canceled.'
                ], 422);
            }

            if ($appointment->status === 'CONFIRMED') {
                $appointmentStart = Carbon::parse($appointment->start_time);

                if (now()->diffInHours($appointmentStart, false) < 24) {
                    return response()->json([
                        'message' => 'Confirmed appointments can only be canceled at least 24 hours before the start time.'
                    ], 422);
                }
            }
        }

        $delayMinutes = 0;

        if ($request->status === 'LATE') {
            $startTime = Carbon::parse($appointment->start_time);
            $now = Carbon::now();

            $delayMinutes = $now->greaterThan($startTime)
                ? $startTime->diffInMinutes($now)
                : 0;
        }

        $appointment->update([
            'status' => $request->status,
            'delay_minutes' => $delayMinutes,
            'cancellation_reason' => $request->status === 'CANCELED'
                ? $request->cancellation_reason
                : $appointment->cancellation_reason,
        ]);

        $appointment->refresh();

        if ($request->status === 'CANCELED' && $appointment->client?->email) {
            $reason = $appointment->cancellation_reason
                ? "\nReason: {$appointment->cancellation_reason}\n"
                : "\nReason was not provided.\n";

            Mail::raw(
                "Hello {$appointment->client->name},\n\n" .
                "Your appointment on {$appointment->start_time} has been canceled by the specialist.\n" .
                $reason . "\n" .
                "Please log in to SmartSchedule to book another appointment.\n\n" .
                "SmartSchedule",
                function ($message) use ($appointment) {
                    $message->to($appointment->client->email)
                        ->subject('Your appointment has been canceled');
                }
            );
        }

        return response()->json([
            'message' => 'Appointment status updated successfully',
            'appointment' => $appointment->fresh()->load(['client', 'services'])
        ]);
    }

    public function confirm($id)
    {
        $appointment = Appointment::findOrFail($id);
        $user = auth()->user();

        if ($user->role !== 'CLIENT' || (int) $appointment->client_id !== (int) $user->id) {
            return response()->json([
                'message' => 'You can confirm only your own appointments',
            ], 403);
        }

        if ($appointment->status !== 'SCHEDULED') {
            return response()->json([
                'message' => 'Only scheduled appointments can be confirmed',
            ], 422);
        }

        $now = Carbon::now();
        $appointmentStart = Carbon::parse($appointment->start_time);

        if(! $now->isSameDay($appointmentStart->copy()->subDay())) {
            return response()->json(['message' => 'Appointment can only be confirmed exactly one day before the reservation'], 422);
        }

        $appointment->update(['status' => 'CONFIRMED']);

        return response()->json([
            'message' =>'Appointment confirmed succesfully',
            'appointment' => $appointment,
        ]);
    }

    public function sendConfirmationEmail($id)
    {
        $appointment = Appointment::with('client')->findOrFail($id);

        $user = auth()->user();

        if (
            $user->role !== 'CLIENT' ||
            (int) $appointment->client_id !== (int) $user->id
        ) {
            return response()->json([
                'message' => 'You can request confirmation only for your own appointments',
            ], 403);
        }

        if ($appointment->status !== 'SCHEDULED') {
            return response()->json([
                'message' => 'Only scheduled appointments can be confirmed',
            ], 422);
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

        return response()->json([
            'message' => 'Confirmation email sent successfully',
        ]);
    }

    public function confirmByEmail($id, $hash)
    {
        $appointment = Appointment::with('client')->findOrFail($id);

        $expectedHash = sha1(
            $appointment->id . '|' .
            $appointment->client->email . '|' .
            $appointment->start_time
        );

        if (! hash_equals($expectedHash, $hash)) {
            return response()->json([
                'message' => 'Invalid confirmation link.'
            ], 422);
        }

        if ($appointment->status === 'CONFIRMED') {
            return response()->json([
                'message' => 'Appointment is already confirmed.',
                'appointment' => $appointment,
            ]);
        }

        if ($appointment->status !== 'SCHEDULED') {
            return response()->json([
                'message' => 'Only scheduled appointments can be confirmed.'
            ], 422);
        }

        $now = Carbon::now();
        $appointmentStart = Carbon::parse($appointment->start_time);

        if (! $now->isSameDay($appointmentStart->copy()->subDay())) {
            return response()->json([
                'message' => 'Appointment can only be confirmed one day before the reservation.'
            ], 422);
        }

        $appointment->update([
            'status' => 'CONFIRMED',
        ]);

        return response()->json([
            'message' => 'Appointment confirmed successfully.',
            'appointment' => $appointment,
        ]);
    }
}
