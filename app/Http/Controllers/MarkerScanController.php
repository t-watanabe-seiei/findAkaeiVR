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
            'scannedAt' => 'required|date',
            'captureType' => 'nullable|string|in:marker_scan,ball_hit'
        ]);
        
        $sessionId = session()->getId();
        $fingerprint = $validated['fingerprint'];
        $markerId = $validated['markerId'];
        $captureType = $validated['captureType'] ?? 'ball_hit';
        
        // marker_scanの場合、当日の重複チェック
        if ($captureType === 'marker_scan') {
            $today = now()->toDateString();
            $existingToday = MarkerScan::where('fingerprint', $fingerprint)
                ->where('marker_id', $markerId)
                ->where('capture_type', 'marker_scan')
                ->whereDate('scanned_at', $today)
                ->exists();
            
            if ($existingToday) {
                return response()->json([
                    'success' => false,
                    'message' => 'Already recorded today',
                    'duplicate' => true
                ]);
            }
        }
        
        // 同じセッション・フィンガープリント・マーカー・タイプの累積スキャン回数を取得
        $totalScans = MarkerScan::where(function($query) use ($sessionId, $fingerprint) {
                $query->where('session_id', $sessionId)
                      ->orWhere('fingerprint', $fingerprint);
            })
            ->where('marker_id', $markerId)
            ->where('capture_type', $captureType)
            ->count() + 1;
        
        // 記録を保存
        $scan = MarkerScan::create([
            'session_id' => $sessionId,
            'fingerprint' => $fingerprint,
            'marker_id' => $markerId,
            'marker_name' => $validated['markerName'],
            'scan_count' => $totalScans,
            'capture_type' => $captureType,
            'scanned_at' => now(), // 現在のJST時刻を使用
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
