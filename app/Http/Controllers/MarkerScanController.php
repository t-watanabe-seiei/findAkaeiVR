<?php

namespace App\Http\Controllers;

use App\Models\MarkerScan;
use Illuminate\Http\Request;

class MarkerScanController extends Controller
{
    public function record(Request $request)
    {
        $validated = $request->validate([
            'markerId' => 'required|string',
            'markerName' => 'required|string',
            'fingerprint' => 'required|string',
            'deviceInfo' => 'required|array',
            'scannedAt' => 'required|date'
        ]);
        
        $sessionId = session()->getId();
        $fingerprint = $validated['fingerprint'];
        $markerId = $validated['markerId'];
        
        // 同じセッション・フィンガープリント・マーカーの累積スキャン回数を取得
        $totalScans = MarkerScan::where(function($query) use ($sessionId, $fingerprint) {
                $query->where('session_id', $sessionId)
                      ->orWhere('fingerprint', $fingerprint);
            })
            ->where('marker_id', $markerId)
            ->count() + 1;
        
        // 記録を保存
        $scan = MarkerScan::create([
            'session_id' => $sessionId,
            'fingerprint' => $fingerprint,
            'marker_id' => $markerId,
            'marker_name' => $validated['markerName'],
            'scan_count' => $totalScans,
            'scanned_at' => $validated['scannedAt'],
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
            'device_info' => $validated['deviceInfo']
        ]);
        
        return response()->json([
            'success' => true,
            'totalScans' => $totalScans,
            'scanId' => $scan->id
        ]);
    }
}
