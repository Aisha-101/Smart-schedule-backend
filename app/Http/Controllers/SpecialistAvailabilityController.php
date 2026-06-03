<?php

namespace App\Http\Controllers;
use App\Models\SpecialistAvailability;
use Illuminate\Http\Request;
use App\Models\Appointment;

class SpecialistAvailabilityController extends Controller
{
    public function index($id)
    {
        return SpecialistAvailability::where('specialist_id', $id)
            ->whereDate('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
    }
    public function store(Request $request, $id)
    {

        $request->validate([
            'date'=>'required|date',
            'start_time'=>'required|date_format:H:i',
            'end_time'=>'required|after:start_time|date_format:H:i'
        ]);
        
        $conflict = SpecialistAvailability::where('specialist_id', $id)
            ->where('date', $request->date)
            ->where(function($q) use($request) {
                $q->whereBetween('start_time', [$request->start_time, $request->end_time])
                  ->orWhereBetween('end_time', [$request->start_time, $request->end_time])
                  ->orWhere(function($subQ) use($request) {
                      $subQ->where('start_time', '<=', $request->start_time)
                           ->where('end_time', '>=', $request->end_time);
                  });
            })
            ->exists();

        if($conflict){
            return response()->json([
            'error'=>'Overlapping availability'
            ],422);
        }
        return SpecialistAvailability::create([
            'specialist_id'=>$id,
            'date'=>$request->date,
            'start_time'=>$request->start_time,
            'end_time'=>$request->end_time
        ]);
    }
    public function update(Request $request, $id)
    {
        $availability = SpecialistAvailability::findOrFail($id);

        $request->validate([
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
        ]);

        $conflict = SpecialistAvailability::where('specialist_id', $availability->specialist_id)
            ->where('id', '!=', $availability->id)
            ->where('date', $request->date)
            ->where(function ($q) use ($request) {
                $q->where('start_time', '<', $request->end_time)
                ->where('end_time', '>', $request->start_time);
            })
            ->exists();

        if ($conflict) {
            return response()->json([
                'error' => 'Overlapping availability'
            ], 422);
        }

        $appointmentsOutsideNewSlot = Appointment::where('specialist_id', $availability->specialist_id)
            ->whereDate('start_time', $availability->date)
            ->whereIn('status', ['SCHEDULED', 'CONFIRMED'])
            ->where(function ($q) use ($request) {
                $q->whereTime('start_time', '<', $request->start_time)
                ->orWhereTime('end_time', '>', $request->end_time);
            })
            ->exists();

        if ($appointmentsOutsideNewSlot) {
            return response()->json([
                'error' => 'Cannot update availability because existing appointments would be outside the new time range.'
            ], 422);
        }

        $availability->update([
            'date' => $request->date,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
        ]);

        return response()->json($availability);
    }

    public function destroy($id)
    {
        $availability = SpecialistAvailability::findOrFail($id);

        $hasAppointments = Appointment::where('specialist_id', $availability->specialist_id)
            ->whereDate('start_time', $availability->date)
            ->whereIn('status', ['SCHEDULED', 'CONFIRMED'])
            ->where(function ($q) use ($availability) {
                $q->whereTime('start_time', '<', $availability->end_time)
                ->whereTime('end_time', '>', $availability->start_time);
            })
            ->exists();

        if ($hasAppointments) {
            return response()->json([
                'error' => 'Cannot delete availability because it has active appointments.'
            ], 422);
        }

        $availability->delete();

        return response()->json([
            'message' => 'Availability deleted successfully'
        ]);
    }
}
