<?php

declare(strict_types=1);

use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/webhook', [TelegramWebhookController::class, 'index']);
