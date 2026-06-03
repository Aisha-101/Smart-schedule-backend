<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        return User::with(['specialist:id,user_id,specialization,workload_factor'])
            ->select('id', 'name', 'email', 'role', 'email_verified_at', 'created_at', 'updated_at')
            ->where('id', '!=', $request->user()->id)
            ->where('is_deleted', false)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function show($id)
    {
        return User::with(['specialist:id,user_id,specialization,workload_factor'])
            ->findOrFail($id);
    }

    public function updateRole(Request $request, $id)
    {
        $request->validate([
            'role' => 'required|in:CLIENT,SPECIALIST,ADMIN',
        ]);

        $user = User::findOrFail($id);

        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'You cannot change your own role.'
            ], 403);
        }

        $user->role = $request->role;
        $user->save();

        return response()->json([
            'message' => 'User role updated successfully.',
            'user' => $user
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if ($request->user()->id === $user->id) {
            return response()->json([
                'message' => 'You cannot delete your own account.'
            ], 403);
        }

        $user->update([
            'name' => 'Deleted user',
            'email' => 'deleted_user_' . $user->id . '@deleted.local',
            'password' => bcrypt(str()->random(32)),
            'remember_token' => null,
            'email_verified_at' => null,
            'is_deleted' => true,
        ]);

        return response()->json([
            'message' => 'User deleted successfully. Appointments were kept for statistics.'
        ]);
    }
}