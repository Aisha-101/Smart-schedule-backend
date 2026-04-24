<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SpecialistAvailabilityController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\SpecialistController;
use App\Http\Controllers\RecommendationController;
use Illuminate\Support\Facades\Route;

// PUBLIC
Route::post('/register',[AuthController::class,'register']);
Route::post('/login',[AuthController::class,'login']);
Route::get('/services',[ServiceController::class,'index']);

// PROTECTED
Route::middleware('auth:api')->group(function(){

Route::get('/appointments',[AppointmentController::class,'index']);
Route::post('/appointments',[AppointmentController::class,'store']);
Route::put('/appointments/{id}',[AppointmentController::class,'update']);
Route::delete('/appointments/{id}',[AppointmentController::class,'destroy']);

Route::get('/appointments/my',[AppointmentController::class,'my']);

Route::get('/specialists/{id}/schedule',[SpecialistAvailabilityController::class,'index']);

Route::get('/recommendations',[RecommendationController::class,'get']);

});