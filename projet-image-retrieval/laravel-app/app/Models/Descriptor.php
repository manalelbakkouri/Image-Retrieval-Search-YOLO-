<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Descriptor extends Model
{
    protected $fillable = [
        'detection_id',
        'color_hist', 'dominant_colors',
        'gabor', 'tamura', 'hu_moments',
        'orientation_hist', 'extra',
        'feature_vector'
    ];

    protected $casts = [
        'color_hist' => 'array',
        'dominant_colors' => 'array',
        'gabor' => 'array',
        'tamura' => 'array',
        'hu_moments' => 'array',
        'orientation_hist' => 'array',
        'extra' => 'array',
        'feature_vector' => 'array',
    ];

    public function detection()
    {
        return $this->belongsTo(Detection::class);
    }
}
