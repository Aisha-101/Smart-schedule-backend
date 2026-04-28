<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Service;
use Illuminate\Support\Facades\Auth;

class ServiceController extends Controller
{
    public function index()
    {
        return Service::all();
    }

    public function store(Request $request)
    {
        try{
            $request->validate([
                'name' => 'required',
                'duration' => 'required|integer',
                'price' => 'required|numeric'
            ]);

           $service = Service::create([
                'name'=>$request->name,
                'duration'=>$request->duration,
                'price'=>$request->price,
                'specialist_id'=>auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Service created successfully',
                'data' => $service
            ], 201);

        } catch(\Exception $e){
            return response()->json([
                'success' => false,
                'message' => 'Failed to create service',
                'error' => $e->getMessage()
            ],500);
        }
    }

    public function update(Request $request, $id)
    {
        $service = Service::findOrFail($id);
        $service->update($request->all());
        return $service;
    }

    public function destroy($id)
    {
        Service::finOrFail($id)->delete();
        return response()->json(['message'=>'Service deleted']);
    }

    public function myServices()
    {
        return Service::where(
            'specialist_id', auth()->id()
        )->get();
    }
}
