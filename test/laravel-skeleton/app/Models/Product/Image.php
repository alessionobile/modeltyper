<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    protected $table = 'product_images';

    protected $fillable = [
        'product_id',
        'url',
        'caption',
        'is_primary',
        'sort_order',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(\App\Models\Product\Product::class);
    }
}