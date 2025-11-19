<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarkerScan extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'session_id',
        'fingerprint',
        'marker_id',
        'marker_name',
        'scan_count',
        'scanned_at',
        'user_agent',
        'ip_address',
        'device_info'
    ];
    
    protected $casts = [
        'device_info' => 'array',
        'scanned_at' => 'datetime'
    ];
}
