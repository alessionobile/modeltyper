<?php

namespace App\Models\Box;

use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    protected $table = 'box_images';

    protected $fillable = [
        'box_id',
        'url',
        'alt_text',
        'width',
        'height',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function box()
    {
        return $this->belongsTo(\App\Models\Box\Box::class);
    }
}