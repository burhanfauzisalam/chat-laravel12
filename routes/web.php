<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\HistoryMessageController;
use App\Http\Controllers\DeepseekController;
use App\Http\Controllers\GeminiController;
use App\Http\Controllers\GroqController;
use App\Http\Controllers\DataAssistantController;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.attempt');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', [ChatController::class, 'index'])->name('chat.index');
    Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');
    Route::get('/messages/history', [HistoryMessageController::class, 'index'])->name('messages.history');
    Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');
    Route::get('/rooms/join/{room}', [RoomController::class, 'join'])->name('rooms.join');
    Route::post('/rooms/{room}/leave', [RoomController::class, 'leave'])->name('rooms.leave');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');

    Route::post('/gemini/chat', [GeminiController::class, 'chat'])->name('gemini.chat');
    Route::post('/deepseek/chat', [DeepseekController::class, 'chat'])->name('deepseek.chat');
    Route::post('/groq/chat', [GroqController::class, 'chat'])->name('groq.chat');
    Route::post('/dataassistant/chat', [DataAssistantController::class, 'chat'])->name('dataassistant.chat');
});
