<?php

namespace App\Http\Controllers;

use App\Models\PrizeExchange;
use Illuminate\Http\Request;

class PrizeExchangeController extends Controller
{
    public function checkStatus(Request $request)
    {
        $fingerprint = $request->input('fingerprint');
        $sessionId = session()->getId();
        
        $exchange = PrizeExchange::where('session_id', $sessionId)
            ->orWhere('fingerprint', $fingerprint)
            ->first();
        
        return response()->json([
            'hasExchanged' => $exchange !== null,
            'prizeCode' => $exchange ? $exchange->prize_code : null
        ]);
    }
    
    public function exchange(Request $request)
    {
        $validated = $request->validate([
            'fingerprint' => 'required|string',
            'deviceInfo' => 'required|array',
            'stamps' => 'required|array'
        ]);
        
        $sessionId = session()->getId();
        $fingerprint = $validated['fingerprint'];
        
        // 既に交換済みかチェック
        $existing = PrizeExchange::where('session_id', $sessionId)
            ->orWhere('fingerprint', $fingerprint)
            ->first();
        
        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'すでに景品を交換済みです',
                'prizeCode' => $existing->prize_code
            ]);
        }
        
        // 景品コードを生成（6桁の英数字）
        do {
            $prizeCode = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
            $exists = PrizeExchange::where('prize_code', $prizeCode)->exists();
        } while ($exists);
        
        // データベースに保存
        $exchange = PrizeExchange::create([
            'session_id' => $sessionId,
            'fingerprint' => $fingerprint,
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
            'device_info' => $validated['deviceInfo'],
            'stamps_data' => $validated['stamps'],
            'prize_code' => $prizeCode,
            'exchanged_at' => now()
        ]);
        
        return response()->json([
            'success' => true,
            'prizeCode' => $prizeCode,
            'message' => '景品交換が完了しました'
        ]);
    }
}
