<?php

namespace App\Models\Box;

use Illuminate\Database\Eloquent\Model;
use App\Models\Product\Product;

class BoxProduct extends Model
{
    protected $table = 'box_products';

    protected $fillable = [
        'box_id',
        'product_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function box()
    {
        return $this->belongsTo(Box::class);
    }
    
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}