<?php

namespace App\Http\Controllers;

use App\Models\MarkerScan;
use App\Models\PrizeExchange;
use Illuminate\Http\Request;

/**
 * ARstampRally202609 専用のスタンプ記録・景品交換コントローラー。
 *
 * 従来の共有API（/api/record-marker-scan 等）は CSRF・セッションが無効な
 * api グループで、閾値もサーバー側で検証されていなかったため、202609 は
 * これらの専用エンドポイント（web グループ = CSRF + セッション有効）へ
 * 切り替える。このクラスは202609の閾値（10種）をサーバー側で強制する。
 */
class StampRally202609Controller extends Controller
{
    /**
     * 景品交換に必要なマーカー種数。
     * クライアント側 head.blade.php の PRIZE_EXCHANGE_THRESHOLD(10) と一致させること。
     */
    public const PRIZE_EXCHANGE_THRESHOLD = 10;

    /**
     * マーカー検出・ボール捕獲の記録。
     */
    public function recordScan(Request $request)
    {
        $validated = $request->validate([
            'markerId' => 'required|string',
            'markerName' => 'required|string',
            'fingerprint' => 'required|string',
            'deviceInfo' => 'required|array',
            'scannedAt' => 'required|date',
            'captureType' => 'nullable|string|in:marker_scan,ball_hit'
        ]);

        $fingerprint = $validated['fingerprint'];
        $markerId = $validated['markerId'];
        $captureType = $validated['captureType'] ?? 'ball_hit';

        // web グループのためセッションが有効。フィンガープリントをセッションに
        // 紐付け、以降の check-prize / exchange-prize でも同一セッションで識別可能にする。
        session(['ar_fingerprint' => $fingerprint]);

        $sessionId = session()->getId();

        // marker_scan の場合、当日の重複チェック
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
        $totalScans = MarkerScan::where(function ($query) use ($sessionId, $fingerprint) {
                $query->where('session_id', $sessionId)
                      ->orWhere('fingerprint', $fingerprint);
            })
            ->where('marker_id', $markerId)
            ->where('capture_type', $captureType)
            ->count() + 1;

        $scan = MarkerScan::create([
            'session_id' => $sessionId,
            'fingerprint' => $fingerprint,
            'marker_id' => $markerId,
            'marker_name' => $validated['markerName'],
            'scan_count' => $totalScans,
            'capture_type' => $captureType,
            'scanned_at' => now(),
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

    /**
     * 景品交換済みかどうかの確認。
     */
    public function checkStatus(Request $request)
    {
        // セッション（web グループで有効化）のフィンガープリントを優先し、
        // リクエスト値にフォールバックする。
        $fingerprint = session('ar_fingerprint') ?: $request->input('fingerprint');
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

    /**
     * 景品交換（景品コード発行）。
     */
    public function exchange(Request $request)
    {
        $validated = $request->validate([
            'fingerprint' => 'required|string',
            'deviceInfo' => 'required|array',
            'stamps' => 'required|array'
        ]);

        // P0-1: サーバー側で閾値（10種以上）を強制する。
        // クライアント単独の閾値では、1枚の偽スタンプでもコード発行できてしまう
        // 脆弱性を閉じる。
        $stampCount = count($validated['stamps']);
        if ($stampCount < self::PRIZE_EXCHANGE_THRESHOLD) {
            return response()->json([
                'success' => false,
                'message' => sprintf('景品交換には%d種類以上のマーカーが必要です', self::PRIZE_EXCHANGE_THRESHOLD)
            ], 422);
        }

        $fingerprint = $validated['fingerprint'];
        $sessionId = session()->getId();

        // セッションにフィンガープリントを保持し、同一セッションで識別可能にする。
        session(['ar_fingerprint' => $fingerprint]);

        // 既に交換済みかチェック（セッション OR フィンガープリント）
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

        // 景品コードを生成（短い形式: 2文字 + 3桁、例: AB147）— 重複チェック付き
        // 可能な組合せ: 26*26*1000 = 676,000 通り
        $attempts = 0;
        do {
            $first = chr(65 + random_int(0, 25));
            $second = chr(65 + random_int(0, 25));
            $digits = str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);
            $prizeCode = $first . $second . $digits;
            $exists = PrizeExchange::where('prize_code', $prizeCode)->exists();

            $attempts++;
            if ($attempts > 20000) {
                $prizeCode = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
                break;
            }
        } while ($exists);

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