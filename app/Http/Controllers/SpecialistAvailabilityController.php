<?php

namespace App\Http\Controllers;
use App\Models\SpecialistAvailability;
use Illuminate\Http\Request;

class SpecialistAvailabilityController extends Controller
{
    public function index($id)
    {
        return SpecialistAvailability::where('specialist_id', $id)->get();
    }
    public function store(Request $request, $id)
    {
        $request->validate([
            'date'=>'required|date',
            'start_time'=>'required',
            'end_time'=>'required|after:start_time'
        ]);
        $conflict = SpecialistAvailability::where('specialist_id',$id)
        ->where('date',$request->date)
        ->where(function($q) use($request){

            $q->whereBetween(
                'start_time',
                [$request->start_time,$request->end_time]
            )

            ->orWhereBetween(
                'end_time',
                [$request->start_time,$request->end_time]
            );

        })->exists();

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
}
