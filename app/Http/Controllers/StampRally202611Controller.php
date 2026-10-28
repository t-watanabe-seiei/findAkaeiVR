<?php

namespace App\Http\Controllers;

use App\Models\MarkerScan;
use App\Models\Stamp202611VoucherApplication;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

/**
 * ARstampRally202611 専用のスタンプ記録・抽選応募コントローラー（design.md §5）。
 *
 * 202610 からの主な変更点:
 *  - 景品コード交換（exchange / PrizeExchange / PRIZE_EXCHANGE_THRESHOLD）を廃止し、
 *    お菓子のガラポン（クライアントのみ・design §5.5）と市内共通商品券の抽選応募
 *    （applyVoucher）へ置き換える。
 *  - 収集種数・当選倍率はすべてサーバー側で算出・強制する（FR-41〜FR-44）。
 *  - Q-12 案A（FR-50 / FR-51）: 収集期間（collect_start 〜 collect_end）と
 *    応募期限（apply_deadline）を分離し、オフラインで収集した日付を保持したまま
 *    帰宅後に応募できる。
 *
 * タイムゾーンの実務注記:
 *   APP_TIMEZONE=Asia/Tokyo で marker_scans.scanned_at は JST 壁時計の文字列として
 *   保存される（now() も JST）。したがって収集期間・応募期限は Asia/Tokyo で parse
 *   してそのまま比較し、クライアントが送る UTC ISO8601（new Date().toISOString()）は
 *   保存前に Asia/Tokyo へ変換して既存データと同一の壁時計系に揃える。
 */
class StampRally202611Controller extends Controller
{
    /**
     * 収集期間 [start, end]（config/ar202611.php・JST）。
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function collectPeriod(): array
    {
        return [
            Carbon::parse((string) config('ar202611.collect_start'), 'Asia/Tokyo'),
            Carbon::parse((string) config('ar202611.collect_end'), 'Asia/Tokyo'),
        ];
    }

    /**
     * 応募期限（config/ar202611.php・JST）。collect_end より後ろになりうる（FR-50）。
     */
    public static function applyDeadline(): Carbon
    {
        return Carbon::parse((string) config('ar202611.apply_deadline'), 'Asia/Tokyo');
    }

    /**
     * 抽選応募に必要な収集種数。
     */
    public static function voucherThreshold(): int
    {
        return (int) config('ar202611.voucher_threshold');
    }

    /**
     * 倍率テーブル（閾値昇順）。
     *
     * @return array<int, int>
     */
    private static function weights(): array
    {
        $weights = (array) config('ar202611.voucher_weights');
        ksort($weights);

        return $weights;
    }

    /**
     * 収集種数から当選倍率（1 / 2 / 3）を返す。閾値未満は 0。
     */
    public static function weightFor(int $stampCount): int
    {
        $weight = 0;
        foreach (self::weights() as $threshold => $value) {
            if ($stampCount >= (int) $threshold) {
                $weight = (int) $value;
            }
        }

        return $weight;
    }

    /**
     * 収集種数から倍率段階（10 / 15 / 20）を返す。閾値未満は 0。
     */
    public static function tierFor(int $stampCount): int
    {
        $tier = 0;
        foreach (array_keys(self::weights()) as $threshold) {
            if ($stampCount >= (int) $threshold) {
                $tier = (int) $threshold;
            }
        }

        return $tier;
    }

    /**
     * サーバー側で算出した収集種数（マーカー種の distinct 数・FR-50）。
     * クライアントが送る stamps 配列は信用しない。
     */
    public static function stampCountFor(?string $sessionId, ?string $fingerprint): int
    {
        if (!$sessionId && !$fingerprint) {
            return 0;
        }

        [$start, $end] = self::collectPeriod();

        return MarkerScan::where(function ($query) use ($sessionId, $fingerprint) {
                $query->where('session_id', $sessionId);
                if ($fingerprint) {
                    $query->orWhere('fingerprint', $fingerprint);
                }
            })
            ->whereBetween('scanned_at', [$start, $end])
            ->distinct()
            ->count('marker_id');
    }

    /**
     * 応募行の倍率を現在種数へ上方更新する（FR-43）。下方更新は行わない。
     *
     * @return bool 更新が発生したか
     */
    public static function syncVoucherWeight(Stamp202611VoucherApplication $application, int $stampCount): bool
    {
        if ($stampCount <= (int) $application->stamp_count) {
            return false;
        }

        $application->stamp_count = $stampCount;
        $application->tier = self::tierFor($stampCount);
        $application->weight = max(1, self::weightFor($stampCount));
        $application->save();

        return true;
    }

    /**
     * マーカー検出・ボール捕獲の記録（design §5.1）。
     *
     * オフラインキューの再送を想定し、クライアントが送る scannedAt を
     * 次のすべてを満たす場合に限り採用する（FR-51・Q-12 案A）:
     *   1. validate の date を通過（parse 可能）
     *   2. 収集期間 collect_start 〜 collect_end 内
     *   3. now() + 24 時間以下（クライアント時計ずれ・未来日付の混入防止）
     * 条件外はサーバー now() へフォールバックする。
     */
    public function recordScan(Request $request)
    {
        $validated = $request->validate([
            'markerId' => 'required|string',
            'markerName' => 'required|string',
            'fingerprint' => 'required|string',
            'deviceInfo' => 'required|array',
            'scannedAt' => 'required|date',
            'captureType' => 'nullable|string|in:marker_scan,ball_hit',
        ]);

        $fingerprint = $validated['fingerprint'];
        $markerId = $validated['markerId'];
        $captureType = $validated['captureType'] ?? 'ball_hit';

        // web グループのためセッションが有効。フィンガープリントをセッションに紐付け、
        // 以降の check-status / apply-voucher でも同一セッションで識別可能にする。
        session(['ar_fingerprint_202611' => $fingerprint]);

        $sessionId = session()->getId();

        // scannedAt の採用規則（design §5.1）
        [$collectStart, $collectEnd] = self::collectPeriod();
        $client = Carbon::parse($validated['scannedAt']);
        $scannedAt = ($client->between($collectStart, $collectEnd)
                && $client->lte(now()->addHours(24)))
            ? $client->setTimezone('Asia/Tokyo')
            : now();

        // marker_scan の当日重複は「採用した scannedAt の日付（JST）」で判定する
        if ($captureType === 'marker_scan') {
            $existingToday = MarkerScan::where('fingerprint', $fingerprint)
                ->where('marker_id', $markerId)
                ->where('capture_type', 'marker_scan')
                ->whereDate('scanned_at', $scannedAt->toDateString())
                ->exists();

            if ($existingToday) {
                return response()->json([
                    'success' => false,
                    'message' => 'Already recorded today',
                    'duplicate' => true,
                    'scannedAt' => $scannedAt->toIso8601String(),
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
            'scanned_at' => $scannedAt,
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip(),
            'device_info' => $validated['deviceInfo'],
        ]);

        return response()->json([
            'success' => true,
            'totalScans' => $totalScans,
            'scanId' => $scan->id,
            'scannedAt' => $scannedAt->toIso8601String(),
        ]);
    }

    /**
     * 収集種数・ガラポン段階・抽選応募状態の照会（design §5.2・202610 の check-prize 相当）。
     *
     * 応募行が存在する場合は応答前に倍率の上方更新を行うため、
     * 利用者は応募ボタンを再押下せずページ読み込みだけで 2 倍 / 3 倍へ昇格する（FR-43）。
     */
    public function checkStatus(Request $request)
    {
        $fingerprint = session('ar_fingerprint_202611') ?: $request->input('fingerprint');
        $sessionId = session()->getId();

        $stampCount = self::stampCountFor($sessionId, $fingerprint);

        $application = $fingerprint
            ? Stamp202611VoucherApplication::where('fingerprint', $fingerprint)->first()
            : null;

        if (!$application && $sessionId) {
            $application = Stamp202611VoucherApplication::where('session_id', $sessionId)->first();
        }

        $updated = $application
            ? self::syncVoucherWeight($application, $stampCount)
            : false;

        return response()->json([
            'success' => true,
            'stampCount' => $stampCount,
            'candyTiers' => array_map('intval', (array) config('ar202611.candy_tiers')),
            'voucherThreshold' => self::voucherThreshold(),
            'applyDeadline' => self::applyDeadline()->toIso8601String(),
            'voucher' => [
                'applied' => $application !== null,
                'weight' => $application ? (int) $application->weight : 0,
                'stampCount' => $application ? (int) $application->stamp_count : 0,
                'appliedAt' => $application && $application->applied_at
                    ? $application->applied_at->toIso8601String()
                    : null,
                'updated' => $updated,
            ],
        ]);
    }

    /**
     * 市内共通商品券の抽選応募（design §5.3・冪等）。
     *
     * 応募期限（apply_deadline）を過ぎたら 422 deadline_passed。
     * 期限前であれば収集期間（collect_end）経過後でも応募成功する（FR-49・帰宅後応募）。
     */
    public function applyVoucher(Request $request)
    {
        $validated = $request->validate([
            'fingerprint' => 'required|string',
            'deviceInfo' => 'required|array',
            'email' => 'required|email|max:190',
            'name' => 'required|string|max:100',
        ]);

        // 応募期限の判定（FR-50）
        if (now()->gt(self::applyDeadline())) {
            return response()->json([
                'success' => false,
                'error' => 'deadline_passed',
                'message' => '応募期限を過ぎたため応募できません',
                'applyDeadline' => self::applyDeadline()->toIso8601String(),
            ], 422);
        }

        $fingerprint = $validated['fingerprint'];
        // email は trim + strtolower で正規化して保存・比較する（FR-44）
        $email = strtolower(trim($validated['email']));
        $name = trim($validated['name']);
        $sessionId = session()->getId();

        session(['ar_fingerprint_202611' => $fingerprint]);

        // 収集種数はサーバー側の marker_scans から算出する（クライアント値は信用しない）
        $stampCount = self::stampCountFor($sessionId, $fingerprint);

        // 同一 fingerprint の応募は倍率の上方更新のみ（FR-43）。
        // session_id では引かない（別 fingerprint での再応募は email の UNIQUE で止める・FR-44）
        $existing = Stamp202611VoucherApplication::where('fingerprint', $fingerprint)->first();

        if ($existing) {
            $updated = self::syncVoucherWeight($existing, $stampCount);

            return response()->json([
                'success' => true,
                'applied' => true,
                'updated' => $updated,
                'weight' => (int) $existing->weight,
                'stampCount' => (int) $existing->stamp_count,
                'appliedAt' => $existing->applied_at ? $existing->applied_at->toIso8601String() : null,
                'message' => 'すでに応募済みです',
            ]);
        }

        if ($stampCount < self::voucherThreshold()) {
            return response()->json([
                'success' => false,
                'error' => 'not_eligible',
                'stampCount' => $stampCount,
                'voucherThreshold' => self::voucherThreshold(),
                'message' => sprintf(
                    '抽選応募には%d種類以上のスタンプが必要です（現在%d種）',
                    self::voucherThreshold(),
                    $stampCount
                ),
            ], 422);
        }

        // 1 メールアドレス = 1 応募（FR-44）
        if (Stamp202611VoucherApplication::where('email', $email)->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'already_applied',
                'message' => 'すでに応募済みです',
            ], 422);
        }

        try {
            $application = Stamp202611VoucherApplication::create([
                'session_id' => $sessionId,
                'fingerprint' => $fingerprint,
                'email' => $email,
                'name' => $name,
                'stamp_count' => $stampCount,
                'tier' => self::tierFor($stampCount),
                'weight' => max(1, self::weightFor($stampCount)),
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip(),
                'device_info' => $validated['deviceInfo'],
                'applied_at' => now(),
            ]);
        } catch (QueryException $e) {
            // fingerprint / email の UNIQUE 競合（同時リクエスト）
            return response()->json([
                'success' => false,
                'error' => 'already_applied',
                'message' => 'すでに応募済みです',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'applied' => true,
            'updated' => false,
            'weight' => (int) $application->weight,
            'stampCount' => (int) $application->stamp_count,
            'appliedAt' => $application->applied_at->toIso8601String(),
            'message' => '市内共通商品券の抽選会に応募しました',
        ]);
    }
}
