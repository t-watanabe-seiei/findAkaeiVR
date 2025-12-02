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
            'isRedeemed' => $exchange ? $exchange->is_redeemed : false,
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
        
        // 景品コードを生成（短い形式: 2文字 + 3桁、例: AB147） — 重複チェック付き
        // 可能な組合せ: 26*26*1000 = 676,000 通り
        $attempts = 0;
        do {
            // ランダムなアルファベット2文字（大文字） + 3桁（先頭0を許す）
            $first = chr(65 + random_int(0, 25));
            $second = chr(65 + random_int(0, 25));
            $digits = str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);
            $prizeCode = $first . $second . $digits;
            $exists = PrizeExchange::where('prize_code', $prizeCode)->exists();

            $attempts++;
            // 万一多く衝突が発生した場合に備えた安全弁
            if ($attempts > 20000) {
                // フォールバック: 既存の6桁ハッシュ方式に戻す
                $prizeCode = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
                break;
            }
        } while ($exists);
        
        // データベースに保存
        // 記録: 生成時に試行回数（$attempts）を保存しておく — 衝突率監視のため
        $exchange = PrizeExchange::create([
            'session_id' => $sessionId,
            'fingerprint' => $fingerprint,
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
            'device_info' => $validated['deviceInfo'],
            'stamps_data' => $validated['stamps'],
            'prize_code' => $prizeCode,
            'exchanged_at' => now(),
            'generation_attempts' => $attempts
        ]);
        
        return response()->json([
            'success' => true,
            'prizeCode' => $prizeCode,
            'message' => '景品交換が完了しました'
        ]);
    }
}
