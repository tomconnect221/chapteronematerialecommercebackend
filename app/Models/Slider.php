<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Slider extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'image_path',
        'link',
        'title',
        'description',
    ];
}
