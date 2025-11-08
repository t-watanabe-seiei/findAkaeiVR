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
        'game_mode',
        'max_combo',
        'enemies_defeated',
    ];
    
    protected $casts = [
        'score' => 'float',
        'level' => 'integer',
        'max_combo' => 'integer',
        'enemies_defeated' => 'integer',
    ];
}
