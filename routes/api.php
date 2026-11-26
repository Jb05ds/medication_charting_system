<?php

use App\Http\Controllers\PatientController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/patients', [PatientController::class, 'index']);
Route::get('/patients/{patient}/orders', [PatientController::class, 'orders']);
Route::post('/patients/{patient}/orders', [PatientController::class, 'store']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
