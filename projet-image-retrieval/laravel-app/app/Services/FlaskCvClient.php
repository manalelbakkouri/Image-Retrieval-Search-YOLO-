<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class FlaskCvClient
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.flask.url');
    }

    public function detect(string $imagePath)
    {
        return Http::attach(
            'image',
            fopen($imagePath, 'r'),
            basename($imagePath)
        )->post($this->baseUrl . '/detect')->json();
    }

    public function describe(string $imagePath, array $bbox)
    {
        return Http::attach(
            'image',
            fopen($imagePath, 'r'),
            basename($imagePath)
        )->post($this->baseUrl . '/describe', [
            'x1' => $bbox[0],
            'y1' => $bbox[1],
            'x2' => $bbox[2],
            'y2' => $bbox[3],
        ])->json();
    }

    public function indexAdd(int $classId, array $items)
    {
        return Http::post($this->baseUrl . '/index/add', [
            'class_id' => $classId,
            'items' => $items,
        ])->json();
    }

    public function searchSimilar(int $classId, array $vector, int $topK = 10)
    {
        return Http::post($this->baseUrl . '/search-similar', [
            'class_id' => $classId,
            'vector' => $vector,
            'top_k' => $topK,
        ])->json();
    }
}
