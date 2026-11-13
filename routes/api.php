<?php

use App\Http\Controllers\DeviceIngestionController;
use App\Http\Controllers\DarajaPaymentController;
use App\Http\Middleware\AuthenticateMeterDevice;
use Illuminate\Support\Facades\Route;

Route::post('/devices/readings', [DeviceIngestionController::class, 'store'])->middleware([AuthenticateMeterDevice::class, 'throttle:60,1']);
Route::post('/payments/daraja/callback/{token}', [DarajaPaymentController::class, 'callback'])->middleware('throttle:120,1')->name('daraja.callback');
