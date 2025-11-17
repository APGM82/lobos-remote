<?php

use App\Http\Controllers\API\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', [UserController::class, 'index']);
Route::get('/user/{nickname}', [UserController::class, 'show']);
Route::post('/user/{id}', [UserController::class, 'update'])->whereNumber('id');
Route::post('/user', [UserController::class, 'store']);
Route::delete('/user/{id}', [UserController::class, 'destroy'])->whereNumber('id');
