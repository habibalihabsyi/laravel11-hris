<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\FaceRecognitionController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/face/enroll', [FaceRecognitionController::class, 'enroll']);
    Route::post('/face/attendance', [FaceRecognitionController::class, 'attendance']);
});
