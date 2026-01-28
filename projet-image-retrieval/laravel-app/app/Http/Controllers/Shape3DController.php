<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class Shape3DController extends Controller
{
    /* ==============================
     * CATALOGUE
     * ============================== */
    public function models()
    {
        $files = collect(Storage::disk('public')->files('models3d'))
            ->filter(fn ($f) => str_ends_with($f, '.obj'))
            ->map(fn ($f) => basename($f))
            ->values();

        return view('shape3d.models', compact('files'));
    }

    /* ==============================
     * UPLOAD
     * ============================== */
    public function uploadModels(Request $request)
    {
        $request->validate([
            'model' => 'required|file|max:51200'
        ]);

        $file = $request->file('model');

        if ($file->getClientOriginalExtension() !== 'obj') {
            return back()->withErrors(['model' => 'Format .obj requis']);
        }

        $file->storeAs('models3d', $file->getClientOriginalName(), 'public');

        return back()->with('success', 'Modèle uploadé');
    }

    /* ==============================
     * VIEWER
     * ============================== */
    public function showModel(string $filename)
    {
        return view('shape3d.show', compact('filename'));
    }

    /* ==============================
     * BUILD INDEX (IMPORTANT)
     * ============================== */
    public function buildIndex()
    {
        $flask = config('services.flask.base');

        try {
            $response = Http::timeout(600)->post(
                $flask . '/index-3d',
                [
                    'models_dir' => storage_path('app/public/models3d'),
                    'labels_csv' => storage_path('app/labels.csv')
                ]
            );

            if (!$response->ok()) {
                return back()->withErrors([
                    'flask' => 'Erreur Flask : ' . $response->body()
                ]);
            }

            return back()->with('success', 'Index FAISS créé avec succès');

        } catch (\Exception $e) {
            return back()->withErrors([
                'flask' => 'Connexion Flask échouée : ' . $e->getMessage()
            ]);
        }
    }

    /* ==============================
     * SEARCH SIMILAR
     * ============================== */
    public function searchFromModel(Request $request)
    {
        $filename = $request->input('filename');
        $path = storage_path("app/public/models3d/$filename");

        if (!file_exists($path)) {
            return back()->withErrors(['file' => 'Fichier introuvable']);
        }

        $flask = config('services.flask.base');

        try {
            $response = Http::timeout(300)
                ->attach('file', file_get_contents($path), $filename)
                ->post($flask . '/search-3d', ['top_k' => 10]);

            if (!$response->ok()) {
                return back()->withErrors(['flask' => 'Erreur API Flask']);
            }

            $results = data_get($response->json(), 'data.results', []);

            return view('shape3d.results', [
                'query' => $filename,
                'results' => $results
            ]);

        } catch (\Exception $e) {
            return back()->withErrors([
                'flask' => 'Connexion Flask échouée'
            ]);
        }
    }
}
