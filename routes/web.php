<?php

use App\Http\Controllers\Api;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/api/push', [Api::class, 'push']);
Route::get('/api/count', [Api::class, 'count']);
Route::get('/api/queue', [Api::class, 'queue']);
Route::get('/api/get', [Api::class, 'get']);
