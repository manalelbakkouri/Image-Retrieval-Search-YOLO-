<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class Shape3DController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | CATALOGUE
    |--------------------------------------------------------------------------
    */
    public function models()
    {
        $files = collect(Storage::disk('public')->files('models3d'))
            ->filter(fn ($f) => str_ends_with($f, '.obj'))
            ->map(fn ($f) => basename($f))
            ->values();

        return view('shape3d.models', compact('files'));
    }

    /*
    |--------------------------------------------------------------------------
    | UPLOAD (.obj)
    |--------------------------------------------------------------------------
    */
    public function uploadModels(Request $request)
    {
        $request->validate([
            'model' => 'required|file|max:51200',
        ]);

        $file = $request->file('model');

        if (strtolower($file->getClientOriginalExtension()) !== 'obj') {
            return back()->withErrors([
                'model' => 'Le fichier doit être au format .obj'
            ]);
        }

        $file->storeAs(
            'models3d',
            $file->getClientOriginalName(),
            'public'
        );

        return redirect()
            ->route('shape3d.models')
            ->with('success', 'Modèle 3D importé avec succès');
    }


    /*
    |--------------------------------------------------------------------------
    | VIEWER 3D
    |--------------------------------------------------------------------------
    */
    public function showModel(string $filename)
    {
        return view('shape3d.show', compact('filename'));
    }

    /*
    |--------------------------------------------------------------------------
    | SEARCH FROM STORED MODEL
    |--------------------------------------------------------------------------
    */
    public function searchFromModel(Request $request)
    {
        $request->validate([
            'filename' => 'required|string'
        ]);

        $filename = $request->input('filename');
        $path = storage_path("app/public/models3d/$filename");

        if (!file_exists($path)) {
            return back()->withErrors(['model' => 'Fichier introuvable']);
        }

        $flask = config('services.flask.base');

        $resp = Http::timeout(300)
            ->attach('file', file_get_contents($path), $filename)
            ->post("$flask/search-3d");

        if (!$resp->ok()) {
            return back()->withErrors(['flask' => 'Erreur API Flask']);
        }

        $results = data_get($resp->json(), 'data.results', []);

        return view('shape3d.results', compact('filename', 'results'));
    }
}
