<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SpecialistAvailabilityController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\SpecialistController;
use App\Http\Controllers\RecommendationController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// PUBLIC
Route::post('/register',[AuthController::class,'register']);
Route::post('/login',[AuthController::class,'login']);

Route::post('/forgot-password',[AuthController::class,'forgotPassword']);
Route::post('/reset-password',[AuthController::class,'resetPassword']);

Route::get('/services',[ServiceController::class,'index']);
Route::get('/specialists',[SpecialistController::class,'index']);


// PROTECTED
Route::middleware('auth:api')->group(function(){

    Route::get('/appointments',[AppointmentController::class,'index']);
    Route::post('/appointments',[AppointmentController::class,'store']);
    Route::put('/appointments/{id}',[AppointmentController::class,'update']);
    Route::delete('/appointments/{id}',[AppointmentController::class,'destroy']);
    Route::get('/appointments/my',[AppointmentController::class,'my']);

    Route::get('/my-services', [ServiceController::class,'myServices']);
    Route::post('/services', [ServiceController::class, 'store']);
    Route::put('/services/{id}', [ServiceController::class, 'update']);
    Route::delete('/services/{id}', [ServiceController::class, 'destroy']);
    
        
    Route::get('/specialists/{id}/schedule', [SpecialistAvailabilityController::class, 'index']);
    Route::post('/specialists/{id}/schedule', [SpecialistAvailabilityController::class, 'store']);
    Route::put('/specialists/{id}/schedule/{scheduleId}', [SpecialistAvailabilityController::class, 'update']);
    Route::delete('/specialists/{id}/schedule/{scheduleId}', [SpecialistAvailabilityController::class, 'destroy']);
    
    Route::get('/recommendations',[RecommendationController::class,'get']);

});