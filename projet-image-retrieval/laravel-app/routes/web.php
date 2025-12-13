<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\TransformController;
use App\Http\Controllers\SearchController;


Route::resource('images', ImageController::class);
Route::get('/images/{image}/download', [ImageController::class, 'download'])->name('images.download');
Route::post('/images/{image}/transform', [TransformController::class, 'transform'])
    ->name('images.transform');
Route::get('/', [ImageController::class, 'index'])->name('images.index');
Route::get('/images/create', [ImageController::class, 'create'])->name('images.create');
Route::post('/images', [ImageController::class, 'storeMany'])->name('images.storeMany');

Route::get('/images/{image}', [ImageController::class, 'show'])->name('images.show');
Route::delete('/images/{image}', [ImageController::class, 'destroy'])->name('images.destroy');
Route::get('/images/{image}/download', [ImageController::class, 'download'])->name('images.download');

Route::post('/images/{image}/process', [ImageController::class, 'process'])->name('images.process');

Route::post('/search', [SearchController::class, 'search'])->name('search.run');

Route::get('/images/{image}/data', [ImageController::class, 'data'])
    ->name('images.data');
