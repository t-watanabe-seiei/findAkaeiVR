<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrizeExchange extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'session_id',
        'fingerprint',
        'prize_code',
        'user_agent',
        'ip_address',
        'device_info',
        'stamps_data',
        'exchanged_at',
        'is_redeemed',
        'redeemed_at'
    ];
    
    protected $casts = [
        'device_info' => 'array',
        'stamps_data' => 'array',
        'exchanged_at' => 'datetime',
        'redeemed_at' => 'datetime',
        'is_redeemed' => 'boolean'
    ];
}
