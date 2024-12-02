<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| Protected Routes (Requires Authentication)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/update-profile', [ProfileController::class, 'updateProfile']);
    Route::post('/posts', [PostController::class, 'createPost']);
    Route::post('/posts/{postId}', [PostController::class, 'updatePost']);
    Route::post('/posts/{postId}/like', [PostController::class, 'likePost']);
    Route::post('/posts/{postId}/comment', [PostController::class, 'addComment']);
    Route::post('/comments/{commentId}/reply', [PostController::class, 'replyToComment']);
    Route::get('/posts/{postId}/comments', [PostController::class, 'getCommentsWithReplies']);
    Route::get('/posts', [PostController::class, 'getAllPosts']);
    Route::delete('/posts/{postId}', [PostController::class, 'deletePost']);
    Route::delete('/comments/{commentId}', [PostController::class, 'deleteComment']);
});
