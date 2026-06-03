<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name'=>'required|string',
            'email'=>'required|email|unique:users',
            'password'=>'required|min:8|confirmed'
        ]);
        $user = User::create([
            'name'=>$request->name,
            'email'=>$request->email,
            'password'=>Hash::make($request->password),
            'role'=>'CLIENT'
        ]);

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message'=>'Registration successful. Please check your email to verify your account.'
        ]);
    }
    public function login(Request $request)
    {
       $credentials = $request->only ('email', 'password');

       if(!$token = auth('api')->attempt($credentials)){
           return response()->json(['error'=>'Invalid credentials'], 401);
       }

       if(!auth('api')->user()->hasVerifiedEmail()){
           return response()->json(['error'=>'Please verify your email address'], 403);
       }

       return response()->json([
           'token'=>$token,
           'user'=>auth('api')->user()->load('specialist'),
       ]);
    }
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email'=>'required|email|exists:users,email'
        ]);

        Password::sendResetLink($request->only('email'));

        return response()->json([
            'message'=>'Password reset link sent to your email'
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'=>'required',
            'email'=>'required|email|exists:users,email',
            'password'=>'required|min:6|confirmed',
            'password_confirmation'=>'required'
        ]);

        $status = Password::reset(
            $request->only(
                'email',
                'password',
                'password_confirmation',
                'token'
            ),

            function($user, $password){
                $user->password = Hash::make($password);
                $user->setRememberToken(Str::random(60));
                $user->save();
            }
        );
         if($status === Password::PASSWORD_RESET){
            return response()->json(['message'=>'Password reset successfully'], 200);
        }
    
        
        $user = User::where('email', $request->email)->first();
        auth('api')->login($user);
        
        return response()->json([
            'message'=> _($status)], 400);
        
    }

    public function updateProfile(Request $request)
    {
        $user = auth('api')->user();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
            'password' => 'sometimes|required|string|min:8|confirmed',
            'specialization' => 'sometimes|required|string|max:255',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

       $user->update(collect($validated)->except('specialization')->toArray());

        if ($user->role === 'SPECIALIST' && $request->filled('specialization')) {
            $user->specialist()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'specialization' => $request->specialization,
                    'workload_factor' => 1.00,
                ]
            );
        }

        return response()->json($user->fresh()->load('specialist'), 200);
    }

    public function logout()
    {
        auth('api')->logout();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }
}
