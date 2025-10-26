<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShootingScore extends Model
{
    use HasFactory;
    
    protected $table = 'shooting_scores';
    
    protected $fillable = [
        'name',
        'score',
        'level',
    ];
    
    protected $casts = [
        'score' => 'float',
        'level' => 'integer',
    ];
}
