<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SpecialistAvailabilityController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\SpecialistController;
use App\Http\Controllers\RecommendationController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\AdminUserController;


// PUBLIC
Route::post('/register',[AuthController::class,'register']);
Route::get('/email/verify/{id}/{hash}', function ($id, $hash) {
    
    $user = \App\Models\User::findOrFail($id);

    if(!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
        return response()->json(['message' => 'Invalid verification link'], 400);
    }

    if (!$user->hasVerifiedEmail()) {
        $user->markEmailAsVerified();
    }

    return response()->json(['message' => 'Email verified']);
});
Route::post('/email/resend', function (Request $request) {
    $user = \App\Models\User::where('email', $request->email)->first();

    if(!$user) {
        return response()->json(['message' => 'User not found'], 404);
    }

    if ($user->hasVerifiedEmail()) {
        return response()->json(['message' => 'Email already verified'], 400);
    }

    $user->sendEmailVerificationNotification();

    return response()->json(['message' => 'Verification email resent']);
});

Route::post('/login',[AuthController::class,'login']);

Route::get('/appointments/{id}/confirm-email/{hash}', [AppointmentController::class, 'confirmByEmail']);

Route::post('/forgot-password',[AuthController::class,'forgotPassword']);
Route::get('/reset-password/{token}', function ($token) {
    return response()->json([
        'token' => $token
    ]);
})->name('password.reset');
Route::post('/reset-password',[AuthController::class,'resetPassword']);

Route::get('/services',[ServiceController::class,'index']);
Route::get('/specialists',[SpecialistController::class,'index']);


// PROTECTED
Route::middleware('auth:api')->group(function(){

    Route::post('/logout',[AuthController::class, 'logout']);
    Route::get('/appointments',[AppointmentController::class,'index']);
    Route::get('/appointments/my',[AppointmentController::class,'my']);
    
    Route::put('/me', [AuthController::class, 'updateProfile']);

    Route::get('/recommendations',[RecommendationController::class,'get']);

});

Route::middleware(['auth:api','role:ADMIN'])->group(function(){
    Route::get('/appointments',[AppointmentController::class,'index']);

    Route::put('/services/{id}', [ServiceController::class, 'update']);
    Route::delete('/services/{id}', [ServiceController::class, 'destroy']);
    Route::post('/specialists/sync-from-users', [SpecialistController::class, 'syncFromUsers']);
    Route::post('/specialists', [SpecialistController::class, 'store']);
    Route::put('/specialists/{id}', [SpecialistController::class, 'update']);
    Route::delete('/specialists/{id}', [SpecialistController::class, 'destroy']);

    Route::get('/statistics', [StatisticsController::class, 'index']);

    Route::get('/admin/users', [AdminUserController::class, 'index']);
    Route::get('/admin/users/{id}', [AdminUserController::class, 'show']);
    Route::patch('/admin/users/{id}/role', [AdminUserController::class, 'updateRole']);
    Route::delete('/admin/users/{id}', [AdminUserController::class, 'destroy']);
});

Route::middleware(['auth:api','role:SPECIALIST'])->group(function(){
    Route::get('/my-services', [ServiceController::class,'myServices']);
    Route::post('/services', [ServiceController::class, 'store']);

    
    Route::put('/appointments/{id}/status', [AppointmentController::class, 'updateStatus']);
        
    Route::get('/specialists/{id}/schedule', [SpecialistAvailabilityController::class, 'index']);
    Route::post('/specialists/{id}/schedule', [SpecialistAvailabilityController::class, 'store']);
    Route::put('/availability/{id}', [SpecialistAvailabilityController::class, 'update']);
    Route::delete('/availability/{id}', [SpecialistAvailabilityController::class, 'destroy']);
});

Route::middleware(['auth:api','role:CLIENT'])->group(function(){
    Route::post('/appointments',[AppointmentController::class,'store']);
    Route::put('/appointments/{id}',[AppointmentController::class,'update']);
    Route::delete('/appointments/{id}',[AppointmentController::class,'destroy']);
    Route::put('/appointments/{id}/confirm', [AppointmentController::class, 'confirm']);
    Route::post('/appointments/{id}/confirm-email', [AppointmentController::class, 'sendConfirmationEmail']);
    Route::get('/specialists/{specialistId}/services',[ServiceController::class,'bySpecialist']);
});