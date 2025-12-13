<?php

namespace App\Http\Controllers;

use App\Models\Image;
use App\Models\Detection;
use App\Services\FlaskCvClient;
use Illuminate\Http\Request;


use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

use App\Models\Descriptor;
use Illuminate\Support\Carbon;

use Symfony\Component\HttpFoundation\BinaryFileResponse;


class ImageController extends Controller
{
    protected FlaskCvClient $cv;

    public function __construct(FlaskCvClient $cv)
    {
        $this->cv = $cv;
    }

    /**
     * Liste des images
     */
    public function index()
    {
        $images = Image::latest()->get();
        return view('images.index', compact('images'));
    }

    /**
     * Formulaire upload
     */
    public function create()
    {
        return view('images.create');
    }

    /**
     * Upload + appel Flask /detect
     */
    public function storeMany(Request $request)
    {
        $request->validate([
            'images' => 'required',
            'images.*' => 'image|max:10240'
        ]);

        $createdIds = [];

        foreach ($request->file('images') as $file) {
            $path = $file->store('images', 'public');
            $fullPath = storage_path('app/public/' . $path);

            $resp = $this->cv->detect($fullPath);
            if (!($resp['success'] ?? false)) {
                continue; // skip si une image échoue
            }

            $image = Image::create([
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'width' => $resp['image']['width'],
                'height' => $resp['image']['height'],
            ]);

            foreach ($resp['detections'] as $det) {
                Detection::create([
                    'image_id' => $image->id,
                    'class_id' => $det['class_id'],
                    'class_name' => $det['class_name'],
                    'confidence' => $det['confidence'],
                    'x1' => $det['bbox_xyxy'][0],
                    'y1' => $det['bbox_xyxy'][1],
                    'x2' => $det['bbox_xyxy'][2],
                    'y2' => $det['bbox_xyxy'][3],
                ]);
            }

            $createdIds[] = $image->id;
        }

        return redirect()->route('images.index')->with('success', count($createdIds).' image(s) importée(s).');
    }


    /**
     * Détails image + objets détectés
     */
    public function show(Image $image)
    {
        $image->load('detections.descriptor');
        return view('images.show', compact('image'));
    }


    public function data(Image $image)
    {
        $image->load('detections.descriptor');

        $detections = $image->detections->map(function ($d) {
            return [
                "id" => $d->id,
                "class_name" => $d->class_name,
                "confidence" => (float) $d->confidence,
                "bbox" => [(float)$d->x1, (float)$d->y1, (float)$d->x2, (float)$d->y2],
                "has_desc" => (bool) $d->descriptor,
                "desc" => $d->descriptor ? $d->descriptor->toArray() : null,
            ];
        })->values();

        return response()->json([
            "image" => [
                "id" => $image->id,
                "width" => (int) $image->width,
                "height" => (int) $image->height,
            ],
            "detections" => $detections
        ]);
    }
    

    public function download(Image $image): BinaryFileResponse
    {
        $path = Storage::disk('public')->path($image->path);

        if (!file_exists($path)) {
            abort(404, 'Fichier introuvable.');
        }

        $name = $image->original_name ?: basename($path);

        return response()->download($path, $name);
    }
    
    public function destroy(Image $image)
    {
        $disk = Storage::disk('public');

        // Supprimer fichier physique
        if ($image->path && $disk->exists($image->path)) {
            $disk->delete($image->path);
        }
        $image->delete();

                return redirect()->route('images.index')->with('success', 'Image supprimée avec succès.');
    }
    

    public function process(Image $image)
    {
        $image->load('detections.descriptor');

        $fullPath = storage_path('app/public/' . $image->path);

        $itemsByClass = []; // class_id => items[] for /index/add

        foreach ($image->detections as $det) {
            // Skip si déjà traité + indexé
            if ($det->descriptor && $det->indexed_at) {
                continue;
            }

            // 1) Appel Flask /describe sur bbox
            $bbox = [$det->x1, $det->y1, $det->x2, $det->y2];
            $resp = $this->cv->describe($fullPath, $bbox);

            if (!($resp['success'] ?? false)) {
                // on continue sur les autres objets (robuste)
                continue;
            }

            $desc = $resp['descriptors'] ?? $resp['descriptors'] ?? ($resp['descriptors'] ?? null);
            // Notre Flask renvoie: { success, bbox_xyxy, descriptors:{...} }
            $desc = $resp['descriptors'] ?? $resp['descriptors'] ?? null;
            if (!$desc && isset($resp['descriptors']) === false && isset($resp['descriptors']) === false) {
                $desc = $resp['descriptors'] ?? null;
            }

            $payload = $resp['descriptors'] ?? null;
            if (!$payload) {
                // selon ton JSON exact Flask: "descriptors": {..}
                $payload = $resp['descriptors'] ?? ($resp['descriptors'] ?? null);
            }
            $payload = $resp['descriptors'] ?? $resp['descriptors'] ?? $resp['descriptors'] ?? null;

            //  on utilise la clé exacte attendue
            $payload = $resp['descriptors'] ?? null;
            if (!$payload) {
                return back()->withErrors("Réponse Flask /describe invalide (clé descriptors manquante).");
            }

            // 2) Sauvegarder / update Descriptor en DB
            $descriptor = Descriptor::updateOrCreate(
                ['detection_id' => $det->id],
                [
                    'color_hist' => $payload['color_hist_hsv'] ?? [],
                    'dominant_colors' => $payload['dominant_colors_lab'] ?? [],
                    'gabor' => $payload['gabor'] ?? [],
                    'tamura' => $payload['tamura'] ?? [],
                    'hu_moments' => $payload['hu_moments'] ?? [],
                    'orientation_hist' => $payload['orientation_hist'] ?? [],
                    'extra' => [
                        'lbp_hist' => $payload['lbp_hist'] ?? []
                    ],
                    'feature_vector' => $payload['feature_vector'] ?? [],
                ]
            );

            // 3) Préparer item pour FAISS (/index/add) groupé par class_id
            $itemsByClass[$det->class_id][] = [
                'image_id' => $image->id,
                'detection_id' => $det->id,
                'vector' => $descriptor->feature_vector,
            ];
        }

        // 4) Push vers FAISS par classe (plus propre)
        foreach ($itemsByClass as $classId => $items) {
            if (count($items) === 0) continue;

            $respIdx = $this->cv->indexAdd((int)$classId, $items);

            // si ok, marquer indexed_at
            if ($respIdx['success'] ?? false) {
                $detIds = array_map(fn($it) => $it['detection_id'], $items);
                \App\Models\Detection::whereIn('id', $detIds)->update([
                    'indexed_at' => Carbon::now()
                ]);
            }
        }

        return redirect()->route('images.show', $image)
            ->with('success', 'Descripteurs calculés et index FAISS mis à jour.');
    }

}



