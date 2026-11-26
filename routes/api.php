<?php

use App\Http\Controllers\AdministrationController;
use App\Http\Controllers\CareTaskController;
use App\Http\Controllers\DailyChartController;
use App\Http\Controllers\PatientController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/chart', [DailyChartController::class, 'chart']);
Route::get('/tasks', [DailyChartController::class, 'tasks']);
Route::post('/tasks/{task}/complete', [CareTaskController::class, 'complete']);
Route::get('/patients', [PatientController::class, 'index']);
Route::get('/patients/{patient}/orders', [PatientController::class, 'orders']);
Route::post('/patients/{patient}/orders', [PatientController::class, 'store']);
Route::post('/orders/{order}/administrations', [AdministrationController::class, 'store']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
