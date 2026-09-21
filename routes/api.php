<?php

use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ReplicationStatusController;
use Illuminate\Support\Facades\Route;

Route::get('/replication/status', ReplicationStatusController::class)->name('api.replication.status');

Route::apiResource('posts', PostController::class)
    ->only([
        'index',
        'store',
        'show',
    ])
    ->names([
        'index' => 'api.posts.index',
        'store' => 'api.posts.store',
        'show' => 'api.posts.show',
    ]);
