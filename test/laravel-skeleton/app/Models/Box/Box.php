<?php

namespace App\Models\Box;

use Illuminate\Database\Eloquent\Model;

class Box extends Model
{
    protected $table = 'boxes';

    protected $fillable = [
        'name',
        'description',
        'size',
    ];

    public function images()
    {
        return $this->hasMany(Image::class);
    }
}