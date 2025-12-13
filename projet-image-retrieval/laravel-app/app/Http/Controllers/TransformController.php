<?php

namespace App\Http\Controllers;

use App\Models\Image;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TransformController extends Controller
{
    public function transform(Request $request, Image $image)
    {
        $request->validate([
            'type' => 'required|in:resize,crop',
            'width' => 'nullable|integer|min:1|max:5000',
            'height' => 'nullable|integer|min:1|max:5000',
            'detection_id' => 'nullable|integer',
        ]);

        $disk = Storage::disk('public');

        if (!$disk->exists($image->path)) {
            return back()->withErrors(['file' => 'Fichier image introuvable.']);
        }

        // Lire l'image source depuis le disk public
        $bytes = $disk->get($image->path);

        // IMPORTANT: fonctions GD avec "\" (namespace safe)
        $src = @\imagecreatefromstring($bytes);
        if (!$src) {
            return back()->withErrors(['gd' => "Impossible de lire l'image (GD). Vérifie extension=gd dans php.ini."]);
        }

        $srcW = \imagesx($src);
        $srcH = \imagesy($src);

        $type = $request->input('type');

        // -----------------------
        // 1) Construire $dst
        // -----------------------
        if ($type === 'resize') {
            $w = (int) ($request->input('width') ?: $srcW);
            $h = (int) ($request->input('height') ?: $srcH);

            $dst = \imagecreatetruecolor($w, $h);
            \imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $srcW, $srcH);
        } else {
            $detId = (int) $request->input('detection_id');
            if (!$detId) {
                \imagedestroy($src);
                return back()->withErrors(['detection_id' => "Sélectionne un objet pour le découpage."]);
            }

            $det = $image->detections()->where('id', $detId)->first();
            if (!$det) {
                \imagedestroy($src);
                return back()->withErrors(['detection_id' => "Objet introuvable."]);
            }

            // bbox dans l'espace "image" stockée en DB (image->width/height)
            $x1 = (float) $det->x1;  $y1 = (float) $det->y1;
            $x2 = (float) $det->x2;  $y2 = (float) $det->y2;

            // sécuriser bbox
            $x1 = max(0, min($x1, $image->width));
            $x2 = max(0, min($x2, $image->width));
            $y1 = max(0, min($y1, $image->height));
            $y2 = max(0, min($y2, $image->height));

            $bw = max(1, (int) round($x2 - $x1));
            $bh = max(1, (int) round($y2 - $y1));

            // conversion bbox -> coords réelles du fichier (srcW/srcH)
            $sx = (int) round(($x1 / $image->width) * $srcW);
            $sy = (int) round(($y1 / $image->height) * $srcH);
            $sw = (int) round(($bw / $image->width) * $srcW);
            $sh = (int) round(($bh / $image->height) * $srcH);

            $sx = max(0, min($sx, $srcW - 1));
            $sy = max(0, min($sy, $srcH - 1));
            $sw = max(1, min($sw, $srcW - $sx));
            $sh = max(1, min($sh, $srcH - $sy));

            $dst = \imagecreatetruecolor($sw, $sh);
            \imagecopy($dst, $src, 0, 0, $sx, $sy, $sw, $sh);
        }

        // Dimensions finales (IMPORTANT: avant imagedestroy)
        $newW = \imagesx($dst);
        $newH = \imagesy($dst);

        // -----------------------
        // 2) Écrire dans un fichier temp
        // -----------------------
        $outName = 'images/generated_' . $image->id . '_' . Str::random(8) . '.jpg';
        $tmpPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . ('out_' . Str::random(10) . '.jpg');

        // Vérifier que l'écriture a réussi
        $ok = @\imagejpeg($dst, $tmpPath, 92);

        // Libérer ressources GD
        \imagedestroy($src);
        \imagedestroy($dst);

        if (!$ok || !file_exists($tmpPath)) {
            return back()->withErrors(['gd' => "Erreur lors de l'écriture du fichier temporaire. Vérifie les permissions sur le dossier Temp."]);
        }

        // -----------------------
        // 3) Stocker dans le disk public + supprimer temp
        // -----------------------
        $disk->put($outName, file_get_contents($tmpPath));
        @unlink($tmpPath);

        // -----------------------
        // 4) Créer l'entrée DB (width/height NOT NULL)
        // -----------------------
        $new = Image::create([
            'original_name' => 'generated_' . ($image->original_name ?: $image->id) . '.jpg',
            'path' => $outName,
            'width' => $newW,
            'height' => $newH,
            'is_generated' => true,
        ]);

        return redirect()->route('images.show', $new)
            ->with('success', 'Transformation générée (tu peux relancer la détection).');
    }
}
