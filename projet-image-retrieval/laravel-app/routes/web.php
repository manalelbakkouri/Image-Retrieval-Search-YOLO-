<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\TransformController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Shape3DController;


/*
|--------------------------------------------------------------------------
| HOME
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return view('home');
})->name('home');

/*
|--------------------------------------------------------------------------
| =======================
| 2D IMAGE PIPELINE
| =======================
| Images, détection, descripteurs, recherche par similarité
|--------------------------------------------------------------------------
*/
Route::resource('images', ImageController::class);

/* Upload multiple */
Route::post('/images/store-many', [ImageController::class, 'storeMany'])
    ->name('images.storeMany');

/* Transformations */
Route::post('/images/{image}/transform', [TransformController::class, 'transform'])
    ->name('images.transform');

/* Post-processing */
Route::post('/images/{image}/process', [ImageController::class, 'process'])
    ->name('images.process');

/* Download */
Route::get('/images/{image}/download', [ImageController::class, 'download'])
    ->name('images.download');

/* JSON data (detections + descriptors) */
Route::get('/images/{image}/data', [ImageController::class, 'data'])
    ->name('images.data');

/* CBIR 2D search */
Route::post('/search', [SearchController::class, 'search'])
    ->name('search.run');

/*
|--------------------------------------------------------------------------
| =======================
| 3D SHAPE RETRIEVAL (CBIR 3D)
| =======================
| Local features, Shape Context, FAISS
|--------------------------------------------------------------------------
*/
Route::prefix('shape3d')->name('shape3d.')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Catalogue des modèles 3D uploadés
    |--------------------------------------------------------------------------
    */
    Route::get('/models', [Shape3DController::class, 'models'])
        ->name('models');

    /*
    |--------------------------------------------------------------------------
    | Upload d’un ou plusieurs modèles 3D (.obj)
    |--------------------------------------------------------------------------
    */
    Route::post('/upload', [Shape3DController::class, 'uploadModels'])
        ->name('upload');

    /*
    |--------------------------------------------------------------------------
    | Viewer 3D (un modèle)
    |--------------------------------------------------------------------------
    */
    Route::get('/show/{filename}', [Shape3DController::class, 'showModel'])
        ->name('show');

    /*
    |--------------------------------------------------------------------------
    | Recherche par similarité depuis
    | un modèle déjà uploadé
    |--------------------------------------------------------------------------
    */
    Route::post('/search-from-model', [Shape3DController::class, 'searchFromModel'])
        ->name('search.from.model');

    /*
    |--------------------------------------------------------------------------
    | Recherche par similarité via
    | upload direct (requête 3D)
    |--------------------------------------------------------------------------
    */
    Route::post('/search', [Shape3DController::class, 'search'])
        ->name('search');

    Route::post('/index/{filename}', 
    [Shape3DController::class, 'indexModel']
)->name('index.model');



Route::get('/shape3d/demo', function () {
    return view('shape3d.demo_results');
})->name('shape3d.demo');


});
