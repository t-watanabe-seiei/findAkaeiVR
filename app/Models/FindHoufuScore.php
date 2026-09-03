<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FindHoufuScore extends Model
{
    use HasFactory;

    protected $table = 'findhoufu_scores';

    protected $fillable = [
        'name',
        'score',
        'max_combo',
        'hits',
    ];

    protected $casts = [
        'score' => 'float',
        'max_combo' => 'integer',
        'hits' => 'integer',
    ];
}