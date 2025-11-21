<?php

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

Route::get('/', function () {
    return view('welcome');
});

// ✅ ДОДАНО: Login маршрут
Route::get('/login', function () {
    return redirect('/login/1'); // Автологін для розробки
})->name('login');

Route::get('/login/{userId}', function ($userId) {
    Auth::loginUsingId($userId);
    return redirect()->route('chat.index');
})->name('quick.login');

Route::middleware(['auth'])->group(function () {
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/users', [ChatController::class, 'users'])->name('chat.users');
    Route::post('/chat/create', [ChatController::class, 'createPrivateChat'])->name('chat.create');
    Route::get('/chat/{id}', [ChatController::class, 'show'])->name('chat.show');
    Route::post('/chat/{id}/message', [ChatController::class, 'sendMessage'])->name('chat.message');
});
