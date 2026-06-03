<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ServiceController extends Controller
{
    public function index()
    {
        return Service::with('specialist:id,name,email,role')
            ->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'duration' => 'required|integer|min:15',
            'price' => 'required|numeric|min:0',
        ]);

        $service = Service::create([
            'name' => $request->name,
            'duration' => $request->duration,
            'price' => $request->price,
            'specialist_id' => auth()->id(),
        ]);

        return response()->json($service, 201);
    }

    public function update(Request $request, $id)
    {
        $service = Service::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'duration' => 'sometimes|required|integer|min:15',
            'price' => 'sometimes|required|numeric|min:0',
        ]);

        $service->update($request->only([
            'name',
            'duration',
            'price',
        ]));

        return response()->json([
            'message' => 'Service updated successfully',
            'service' => $service
        ]);
    }

    public function destroy($id)
    {
        $service = Service::findOrFail($id);
        $service->delete();

        return response()->json([
            'message' => 'Service deleted successfully'
        ]);
    }

    public function myServices()
    {
        return Service::where(
                'specialist_id', auth()->id()
            )->get();
    }

    public function bySpecialist($specialistId)
    {
        $specialist = User::where('id', $specialistId)
            ->where('role', 'SPECIALIST')
            ->first();

        if (! $specialist) {
            return response()->json([
                'message' => 'Specialist not found',
            ], 404);
        }

        return Service::where('specialist_id', $specialistId)->get();
    }
}
