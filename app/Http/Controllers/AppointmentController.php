<?php

namespace App\Http\Controllers;
use App\Models\Appointment;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function index()
    {
        return Appointment::with(['services','client','specialist'])->get();
    }
    public function store(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:users,id',
            'specialist_id' => 'required|exists:users,id',
            'services' => 'required|array',
            'services.*' => 'exists:services,id',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time'
        ]);

       $overlap = Appointment::where('specialist_id', $request->specialist_id)
            ->where('status', '!=', 'canceled')
            ->where(function ($query) use ($request){
                $query->whereBetween('start_time',[$request->start_time,$request->end_time])
                    ->orWhereBetween('end_time',[$request->start_time,$request->end_time])
                    ->orWhere(function ($q) use ($request){
                        $q->where('start_time', '<=',$request->start_time)
                            ->where('end_time', '>=',$request->end_time);
                    });
            })
            ->exists();

        if($overlap){
            return response()->json([
                'message' => 'Time slot is already taken'
            ], 400);
        }

        $appointment = Appointment::create([
            'client_id' => $request->client_id,
            'specialist_id' => $request->specialist_id,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'status' => 'SCHEDULED'
        ]);
        $appointment->services()->attach($request->services);

        return $appointment->load('services');
    }

    public function update(Request $request, $id)
    {
        $appointment = Appointment::findOrFail($id);

        $appointment->update($request->only([
            'start_time',
            'end_time',
            'status'
        ]));

        if($request->has('services')){
            $appointment->services()->sync($request->services);
        }

        return $appointment->load('services');
    }

    //Cancel Appointment
    public function destroy($id)
    {
        $appointment = Appointment::findOrFail($id);

        $appointment->update(
            ['status' => 'CANCELED']
        );
        return response()->json(['message' => 'Appointment canceled successfully']);
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

        if($user->role === 'SPECIALIST'){
            return Appointment::with([
                'services',
                'client'
            ])
            ->where('specialist_id', $user->id)
            ->get();
        }

        return response()->json([]);
    }
}
