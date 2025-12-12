<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    protected $fillable = [
        'original_name', 'path', 'width', 'height',
        'is_generated', 'parent_image_id'
    ];

    public function detections()
    {
        return $this->hasMany(Detection::class);
    }
}
