<?php

namespace App\Http\Controllers;

use App\Models\Image;
use App\Models\Detection;
use App\Services\FlaskCvClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240', // 10MB
        ]);

        // 1. Sauvegarde fichier
        $file = $request->file('image');
        $path = $file->store('images', 'public');
        $fullPath = storage_path('app/public/' . $path);

        // 2. Appel Flask /detect
        $response = $this->cv->detect($fullPath);

        if (!($response['success'] ?? false)) {
            return back()->withErrors('Erreur lors de la détection YOLO');
        }

        // 3. Sauvegarde image DB
        $image = Image::create([
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'width' => $response['image']['width'],
            'height' => $response['image']['height'],
        ]);

        // 4. Sauvegarde detections
        foreach ($response['detections'] as $det) {
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

        return redirect()->route('images.show', $image);
    }

    /**
     * Détails image + objets détectés
     */
    public function show(Image $image)
    {
        $image->load('detections.descriptor');
        return view('images.show', compact('image'));
    }
}
