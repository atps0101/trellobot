<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\TrelloController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::any('/', function () {
    abort(404);
});

Route::any('/telegram/webhook', [TelegramController::class, 'handleWebhook']);

Route::get('/trello', [TrelloController::class, 'index']);

Route::get('/trello/callback', [TrelloController::class, 'handleTrelloCallback']);

Route::any('/trello/webhook', [TrelloController::class, 'webhook']);