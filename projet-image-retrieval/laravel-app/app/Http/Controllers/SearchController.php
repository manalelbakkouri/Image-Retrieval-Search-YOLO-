<?php

namespace App\Http\Controllers;

use App\Models\Detection;
use App\Models\Image;
use App\Services\FlaskCvClient;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(protected FlaskCvClient $cv) {}

    public function search(Request $request)
    {
        $request->validate([
            'detection_id' => 'required|exists:detections,id'
        ]);

        $det = Detection::with(['image', 'descriptor'])->findOrFail($request->detection_id);

        if (!$det->descriptor || empty($det->descriptor->feature_vector)) {
            return back()->withErrors("Descripteurs manquants. Clique sur 'Calculer descripteurs + Indexer'.");
        }

        $resp = $this->cv->searchSimilar($det->class_id, $det->descriptor->feature_vector, 12);

        if (!($resp['success'] ?? false)) {
            return back()->withErrors("Recherche échouée (FAISS).");
        }

        $results = $resp['results'] ?? [];

        // récupérer les images correspondantes (via image_id)
        $imageIds = collect($results)->pluck('image_id')->filter()->unique()->values()->all();
        $images = Image::whereIn('id', $imageIds)->get()->keyBy('id');

        return view('search.results', [
            'queryDetection' => $det,
            'results' => $results,
            'images' => $images
        ]);
    }
}

