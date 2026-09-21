<?php

use App\Http\Controllers\ReplicationDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ReplicationDashboardController::class, 'index'])->name('home');
Route::post('/posts', [ReplicationDashboardController::class, 'store'])->name('posts.store');
