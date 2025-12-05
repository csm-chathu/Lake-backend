<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\OwnerController;
use App\Http\Controllers\Api\VeterinarianController;
use App\Http\Controllers\Api\MedicineController;
use App\Http\Controllers\Api\AppointmentController;

// Keep backwards-compatible routes at /api/<resource>
Route::apiResource('patients', PatientController::class);
Route::apiResource('owners', OwnerController::class);
Route::apiResource('veterinarians', VeterinarianController::class);
Route::apiResource('medicines', MedicineController::class);
Route::apiResource('appointments', AppointmentController::class);

// Also expose v1 prefix (optional)
Route::prefix('v1')->group(function () {
    Route::apiResource('patients', PatientController::class);
    Route::apiResource('owners', OwnerController::class);
    Route::apiResource('veterinarians', VeterinarianController::class);
    Route::apiResource('medicines', MedicineController::class);
    Route::apiResource('appointments', AppointmentController::class);
});
