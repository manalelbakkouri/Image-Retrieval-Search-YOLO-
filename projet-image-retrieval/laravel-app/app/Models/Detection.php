<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class Detection extends Model
{
    protected $fillable = [
        'image_id', 'class_id', 'class_name',
        'confidence', 'x1', 'y1', 'x2', 'y2'
    ];

    public function image()
    {
        return $this->belongsTo(Image::class);
    }

    public function descriptor()
    {
        return $this->hasOne(Descriptor::class);
    }
}
