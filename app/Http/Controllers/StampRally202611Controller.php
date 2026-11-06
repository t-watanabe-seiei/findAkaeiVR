<?php

namespace App\Http\Controllers;

use App\Mail\Stamp202611VoucherCodeMail;
use App\Models\MarkerScan;
use App\Models\Stamp202611VoucherApplication;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

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
 *  - FR-56: 応募資格・倍率は 1 端末（fingerprint）単位。
 *  - FR-58: 収集種数は capture_type='ball_hit'（ボールを当ててコレクションに入った種数）のみ。
 *    マーカーを検出しただけの 'marker_scan' は応募資格・倍率に含めず、表示用の参考値とする。
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
     * 応募済みメールアドレスの変更を受理する最終日時（FR-63・config/ar202611.email_change_deadline・JST）。
     * 抽選（11 月末頃）以降は変更を受け付けない。設定値が不正な場合は応募期限へフォールバックする。
     */
    public static function emailChangeDeadline(): Carbon
    {
        try {
            $value = config('ar202611.email_change_deadline');

            // 未設定・空文字は既定値（抽選実施 11 月末）を使う（空文字を parse すると now になる）
            if (!is_string($value) || trim($value) === '') {
                $value = '2026-11-30 23:59:59';
            }

            return Carbon::parse($value, 'Asia/Tokyo');
        } catch (\Throwable $e) {
            return self::applyDeadline();
        }
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
     * 同一ブラウザ（session_id）または同一端末（fingerprint）の合算種数（監査用の参考値・FR-50）。
     * クライアントが送る stamps 配列は信用しない。
     *
     * capture_type を絞らないため「マーカーを見つけた種数」も含まれる。
     * 応募資格と倍率の正本は deviceStampCountFor()（収集のみ・FR-58）。
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
     * 1 端末（= 1 fingerprint）単位の収集種数（FR-56・応募資格と倍率の正本）。
     *
     * session_id を条件に含めない。同一機種 + 同一 userId では fingerprint が一致するため、
     * session_id 経由の合算は別端末の種数を自端末へ誤帰属させる（2026-11-01 の 15 種 2 名）。
     *
     * capture_type = 'ball_hit'（ボールを当ててコレクションに入った種数）のみ数える（FR-58）。
     * マーカーを検出しただけの 'marker_scan' は応募資格・倍率に含めない。
     */
    public static function deviceStampCountFor(?string $fingerprint): int
    {
        if (!$fingerprint) {
            return 0;
        }

        [$start, $end] = self::collectPeriod();

        return MarkerScan::where('fingerprint', $fingerprint)
            ->where('capture_type', 'ball_hit')
            ->whereBetween('scanned_at', [$start, $end])
            ->distinct()
            ->count('marker_id');
    }

    /**
     * 1 端末単位の「マーカー検出種数」（capture_type = 'marker_scan'・FR-58）。
     * 応募資格には使わず、画面表示とスタッフ説明用の参考値。
     */
    public static function detectedStampCountFor(?string $fingerprint): int
    {
        if (!$fingerprint) {
            return 0;
        }

        [$start, $end] = self::collectPeriod();

        return MarkerScan::where('fingerprint', $fingerprint)
            ->where('capture_type', 'marker_scan')
            ->whereBetween('scanned_at', [$start, $end])
            ->distinct()
            ->count('marker_id');
    }

    /**
     * 応募行の合算列（stamp_count / tier）を現在種数へ上方更新する。下方更新は行わない。
     *
     * weight は device_* が未設定の行（grandfathering 対象・未 backfill）だけ合算値で昇格させる（FR-43）。
     * device_* を持つ行の weight は正本 device_weight 側が管理する（FR-56）。
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

        if ($application->device_weight === null) {
            $application->weight = max((int) $application->weight, max(1, self::weightFor($stampCount)));
        }

        $application->save();

        return true;
    }

    /**
     * 応募行の device_* を現在種数から上方更新し、抽選列 weight を正本 device_weight と揃える（FR-43 / FR-56）。
     *
     * 既に付与済みの倍率へは遡及降格しないため device_weight = max(既存 weight, weightFor(現在種数)) とする（FR-57）。
     *
     * @return bool 倍率の昇格が発生したか（device_* の初期化＝backfill は含めない）
     */
    public static function syncDeviceVoucherWeight(Stamp202611VoucherApplication $application, int $deviceCount): bool
    {
        $current = $application->device_stamp_count === null
            ? -1
            : (int) $application->device_stamp_count;

        if ($deviceCount <= $current) {
            return false;
        }

        $granted = max(1, (int) $application->weight);
        $deviceWeight = max($granted, max(1, self::weightFor($deviceCount)));

        $application->device_stamp_count = $deviceCount;
        $application->device_tier = self::tierFor($deviceCount);
        $application->device_weight = $deviceWeight;
        $application->weight = $deviceWeight;
        $application->save();

        return $deviceWeight > $granted;
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
        // 送信された fingerprint を正本、セッション値はフォールバックにする（T104-16）。
        // セッション優先だと ?test=1（test_fp_*）と通常 URL（fp_*）を同一ブラウザで行き来したとき、
        // 直前に hit したページの fingerprint が残り「この端末の収集: 9 種（この端末 10 種）」のように
        // 表示（check-status）と応募判定（apply-voucher は送信 fingerprint）が食い違う。
        $sent       = is_string($request->input('fingerprint')) ? trim($request->input('fingerprint')) : '';
        $fingerprint = $sent !== '' && strlen($sent) <= 64
            ? $sent
            : session('ar_fingerprint_202611');
        $sessionId = session()->getId();

        $stampCount = self::stampCountFor($sessionId, $fingerprint);
        // 応募資格・倍率の正本は 1 端末（fingerprint）単位の「収集種数」。合算値は表示・監査用の参考値（FR-56 / FR-58）
        $deviceCount = self::deviceStampCountFor($fingerprint);
        // マーカーを検出しただけの種数は応募資格に含めないが、利用者の説明用に返す（FR-58）
        $deviceDetectedCount = self::detectedStampCountFor($fingerprint);

        $application = $fingerprint
            ? Stamp202611VoucherApplication::where('fingerprint', $fingerprint)->first()
            : null;

        if (!$application && $sessionId) {
            $application = Stamp202611VoucherApplication::where('session_id', $sessionId)->first();
        }

        $updated = false;
        if ($application) {
            $aggregateUpdated = self::syncVoucherWeight($application, $stampCount);
            $deviceUpdated = self::syncDeviceVoucherWeight($application, $deviceCount);
            $updated = $aggregateUpdated || $deviceUpdated;
        }

        return response()->json([
            'success' => true,
            // 合算（同一ブラウザの session_id / fingerprint・検出込みの参考値）
            'stampCount' => $stampCount,
            // 正本: この端末がボールを当てて収集した種数（FR-56 / FR-58）
            'deviceStampCount' => $deviceCount,
            // この端末がマーカーを見つけた種数（応募資格には含めない・FR-58）
            'deviceDetectedCount' => $deviceDetectedCount,
            'candyTiers' => array_map('intval', (array) config('ar202611.candy_tiers')),
            'voucherThreshold' => self::voucherThreshold(),
            'applyDeadline' => self::applyDeadline()->toIso8601String(),
            // 応募済みメールアドレスの変更期限（FR-63・この日時以降は変更ボタンを出さない）
            'emailChangeDeadline' => self::emailChangeDeadline()->toIso8601String(),
            'voucher' => [
                'applied' => $application !== null,
                // weight は抽選の正本（device_* 設定済みなら device_weight と一致する）
                'weight' => $application ? (int) $application->weight : 0,
                'deviceWeight' => $application
                    ? (int) ($application->device_weight ?? $application->weight)
                    : 0,
                'deviceStampCount' => $application ? (int) $application->device_stamp_count : 0,
                'stampCount' => $application ? (int) $application->stamp_count : 0,
                'appliedAt' => $application && $application->applied_at
                    ? $application->applied_at->toIso8601String()
                    : null,
                // メールアドレス確認の状態（FR-61・確認導入以前の応募は true）
                'emailVerified' => $application ? $application->isEmailVerified() : false,
                // 応募済みメールアドレス（マスク済み・FR-63）。画面には完全なアドレスを返さない
                'email' => $application ? self::maskEmail((string) $application->email) : null,
                'updated' => $updated,
            ],
        ]);
    }

    // ── メールアドレス確認（FR-61 / design §5.7）───────────────────────────

    /**
     * メールアドレス確認が有効か（config/ar202611.email_verification.enabled）。
     * false のときは確認なしで応募できる（SMTP 未設定の開発環境用フォールバック）。
     */
    public static function emailVerificationEnabled(): bool
    {
        return (bool) config('ar202611.email_verification.enabled', true);
    }

    /**
     * 確認パラメータ（code_ttl / max_attempts / resend_interval / dns_check）。
     *
     * @return array{code_ttl:int,max_attempts:int,resend_interval:int,dns_check:bool}
     */
    private static function emailVerificationOptions(): array
    {
        return [
            'code_ttl' => max(60, (int) config('ar202611.email_verification.code_ttl', 900)),
            'max_attempts' => max(1, (int) config('ar202611.email_verification.max_attempts', 5)),
            'resend_interval' => max(0, (int) config('ar202611.email_verification.resend_interval', 30)),
            'dns_check' => (bool) config('ar202611.email_verification.dns_check', false),
        ];
    }

    /**
     * 確認コードの Cache キー（応募と同一単位 = 1 fingerprint で保持する）。
     *
     * $purpose = 'apply' は抽選応募（FR-61）、'change' は応募済みメールアドレスの変更（FR-63）。
     * 変更用のコードと応募用のコードは別キーで保持し、互いに使い回せないようにする。
     */
    private static function emailCodeCacheKey(string $fingerprint, string $purpose = 'apply'): string
    {
        $prefix = $purpose === 'change' ? 'voucher-email-change' : 'voucher-email-code';

        return 'ar202611:' . $prefix . ':' . $fingerprint;
    }

    /**
     * 表示用のマスク済みメールアドレス（例: t***@example.com）。
     * 送信後も完全なアドレスを画面には返さない（FR-61）。
     */
    public static function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $visible = mb_substr($local, 0, 1);

        return $visible . str_repeat('*', max(1, mb_strlen($local) - 1)) . '@' . $domain;
    }

    /**
     * QueryException が SQLite のロック由来かどうか（"database is locked"）。
     * UNIQUE 競合と混同すると「すでに応募済みです」等の誤案内になるため、
     * ロックは呼び出し側の catch で握りつぶさず再スローして 503 server_busy にする。
     */
    private static function isDatabaseLock(QueryException $e): bool
    {
        return str_contains($e->getMessage(), 'database is locked');
    }

    /**
     * メールアドレスのドメインが存在するか（FR-61・dns_check が true のときだけ呼ぶ）。
     *
     * Laravel には `dns` バリデーションルールが存在しない（`email:rfc,dns` の dns 引数とは別物）ため、
     * 未定義ルールによる BadMethodCallException を避ける目的で checkdnsrr() を直接使う。
     * DNS 参照そのものの失敗（ネットワーク障害・共有ホスティングの制限）は応募を止める理由にしないため、
     * 関数不在・例外時は true（通過）を返す。ドメインが解決できない場合だけ false。
     *
     * @param string $email 正規化済みのメールアドレス
     */
    private static function emailDomainExists(string $email): bool
    {
        $at = strrpos($email, '@');
        $domain = $at === false ? '' : substr($email, $at + 1);

        // 不正な形（念のため）と DNS 照会関数が無い環境は通過させる
        if ($domain === '' || !function_exists('checkdnsrr')) {
            return true;
        }

        foreach (['MX', 'A'] as $record) {
            try {
                if (checkdnsrr($domain, $record)) {
                    return true;
                }
            } catch (\Throwable $e) {
                report($e);

                return true; // DNS 参照が失敗した場合は応募を止めない
            }
        }

        return false;
    }

    /**
     * 確認コードの送信（POST /stamp202611/voucher-email-code）。
     *
     * 応募行はこの時点では作らない（未確認の行を抽選 CSV に混ぜない・FR-61）。
     * コードは Cache に TTL 付きで保持し、DB には保存しない。
     */
    public function requestEmailCode(Request $request)
    {
        $options = self::emailVerificationOptions();

        // 'dns' は Laravel のバリデーションルールではない（email:rfc,dns の dns 引数とは別物）ため連結しない。
        // 連結すると Validator::validateDns が未定義で BadMethodCallException（500）になる。
        // ドメイン存在確認は emailDomainExists() で行う（FR-61・dns_check）。
        $validated = $request->validate([
            'fingerprint' => 'required|string',
            'email' => 'required|email|max:190',
        ]);

        // 応募期限経過後は送信しない（FR-50）
        if (now()->gt(self::applyDeadline())) {
            return response()->json([
                'success' => false,
                'error' => 'deadline_passed',
                'applyDeadline' => self::applyDeadline()->toIso8601String(),
                'message' => '応募期限を過ぎたため応募できません',
            ], 422);
        }

        $fingerprint = $validated['fingerprint'];
        // email は trim + strtolower で正規化（FR-44）
        $email = strtolower(trim($validated['email']));

        // ドメイン存在確認（dns_check が true のときだけ・FR-61）
        if ($options['dns_check'] && !self::emailDomainExists($email)) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_email',
                'message' => 'メールアドレスのドメインが見つかりません。入力をご確認ください',
            ], 422);
        }

        session(['ar_fingerprint_202611' => $fingerprint]);

        // 送信前に応募資格を確認する（無駄なメールを送らない・FR-58）
        $deviceCount = self::deviceStampCountFor($fingerprint);
        if ($deviceCount < self::voucherThreshold()) {
            return response()->json([
                'success' => false,
                'error' => 'not_eligible',
                'deviceStampCount' => $deviceCount,
                'voucherThreshold' => self::voucherThreshold(),
                'message' => sprintf(
                    '抽選応募には1端末で%d種類の収集（ボールを当ててコレクションに入った数）が必要です（現在%d種）',
                    self::voucherThreshold(),
                    $deviceCount
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

        // 確認無効（開発環境・SMTP 未設定）: 送信なしでそのまま応募できる
        if (!self::emailVerificationEnabled()) {
            return response()->json([
                'success' => true,
                'verification_required' => false,
                'email' => self::maskEmail($email),
            ]);
        }

        $key = self::emailCodeCacheKey($fingerprint);
        $current = Cache::get($key);

        // 短すぎる間隔での「同じアドレスへの」再送信は断る（送信コスト・乱打対策）。
        // 送信済みアドレスと違うアドレスへの送信は、入力ミスを直した再試行なので間隔を要求しない（FR-61）。
        // 打ち間違いでコードが届かなかった場合に、アドレスを直して即座に再送信できることが必要。
        if (is_array($current) && $options['resend_interval'] > 0
            && ($current['email'] ?? '') === $email) {
            $elapsed = now()->getTimestamp() - (int) ($current['sent_at'] ?? 0);
            if ($elapsed >= 0 && $elapsed < $options['resend_interval']) {
                return response()->json([
                    'success' => false,
                    'error' => 'too_soon',
                    'waitSeconds' => $options['resend_interval'] - $elapsed,
                    'message' => '少し待ってからもう一度送信してください',
                ], 429);
            }
        }

        $code = (string) random_int(100000, 999999);
        $ttlMinutes = (int) ceil($options['code_ttl'] / 60);

        Cache::put($key, [
            'email' => $email,
            'code' => $code,
            'sent_at' => now()->getTimestamp(),
            'expires_at' => now()->getTimestamp() + $options['code_ttl'],
            'attempts' => 0,
        ], $options['code_ttl']);

        try {
            Mail::to($email)->send(new Stamp202611VoucherCodeMail($code, $ttlMinutes));
        } catch (\Throwable $e) {
            // SMTP 障害: コードを残さず破棄して再試行を促す
            Cache::forget($key);
            report($e);

            return response()->json([
                'success' => false,
                'error' => 'mail_failed',
                'message' => '確認メールを送信できませんでした。時間をおいてもう一度お試しください',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'verification_required' => true,
            'email' => self::maskEmail($email),
            'ttlMinutes' => $ttlMinutes,
            'resendInterval' => $options['resend_interval'],
            'message' => '確認コードを送信しました',
        ]);
    }

    /**
     * 確認コードの検証（applyVoucher から呼ぶ・FR-61）。
     *
     * 一致したら Cache を削除して ok = true を返す。不一致時は試行回数を増やし、
     * 上限到達でコードを破棄する（総当たり対策）。
     *
     * $purpose は emailCodeCacheKey() と同一（'apply' = 応募・FR-61 / 'change' = メールアドレス変更・FR-63）。
     *
     * @return array{ok:bool,error?:string,message?:string,remaining_attempts?:int,sent_at?:int|null}
     */
    private static function consumeEmailCode(string $fingerprint, string $email, ?string $code, string $purpose = 'apply'): array
    {
        $options = self::emailVerificationOptions();
        $key = self::emailCodeCacheKey($fingerprint, $purpose);
        $entry = Cache::get($key);

        // 未送信・期限切れ・送信時と異なるメールアドレス
        if (!is_array($entry) || ($entry['email'] ?? '') !== $email) {
            Cache::forget($key);

            return [
                'ok' => false,
                'error' => 'code_expired',
                'message' => '確認コードが無効または期限切れです。新しいコードを送信してください',
            ];
        }

        if ($code === null || $code === '') {
            return [
                'ok' => false,
                'error' => 'code_required',
                'message' => '確認コードを入力してください',
                'remaining_attempts' => max(0, $options['max_attempts'] - (int) $entry['attempts']),
            ];
        }

        if ((int) $entry['attempts'] >= $options['max_attempts']) {
            Cache::forget($key);

            return [
                'ok' => false,
                'error' => 'code_expired',
                'message' => '確認コードの入力回数を超えました。新しいコードを送信してください',
            ];
        }

        if (!hash_equals((string) $entry['code'], $code)) {
            $attempts = (int) $entry['attempts'] + 1;
            $remaining = max(0, $options['max_attempts'] - $attempts);

            if ($remaining === 0) {
                Cache::forget($key);
            } else {
                // 有効期限は延長せず、試行回数だけ更新する
                $entry['attempts'] = $attempts;
                $ttl = max(1, (int) $entry['expires_at'] - now()->getTimestamp());
                Cache::put($key, $entry, $ttl);
            }

            return [
                'ok' => false,
                'error' => 'code_invalid',
                'message' => '確認コードが一致しません',
                'remaining_attempts' => $remaining,
            ];
        }

        Cache::forget($key);

        return [
            'ok' => true,
            'sent_at' => isset($entry['sent_at']) ? (int) $entry['sent_at'] : null,
        ];
    }

    /**
     * 市内共通商品券の抽選応募（design §5.3・冪等）。
     *
     * 応募期限（apply_deadline）を過ぎたら 422 deadline_passed。
     * 期限前であれば収集期間（collect_end）経過後でも応募成功する（FR-49・帰宅後応募）。
     *
     * メールアドレス確認が有効な間は code（requestEmailCode で送信した 6 桁）の一致を
     * 確認してから応募行を作る（FR-61）。同一 fingerprint の再応募（倍率の上方更新）は
     * 確認済み行に対する操作のため code を要求しない。
     */
    public function applyVoucher(Request $request)
    {
        $validated = $request->validate([
            'fingerprint' => 'required|string',
            'deviceInfo' => 'required|array',
            'email' => 'required|email|max:190',
            'name' => 'required|string|max:100',
            // 確認コード（requestEmailCode で送信した 6 桁・FR-61）。確認無効時は省略可
            'code' => 'nullable|string|max:10',
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

        // 収集種数はサーバー側の marker_scans から算出する（クライアント値は信用しない）。
        // 応募資格の判定は 1 端末（fingerprint）単位の収集種数（FR-56 / FR-58）。
        // 合算値（検出込み）は stamp_count に監査用として保存する。
        $stampCount = self::stampCountFor($sessionId, $fingerprint);
        $deviceCount = self::deviceStampCountFor($fingerprint);

        // 同一 fingerprint の応募は倍率の上方更新のみ（FR-43）。
        // session_id では引かない（別 fingerprint での再応募は email の UNIQUE で止める・FR-44）
        $existing = Stamp202611VoucherApplication::where('fingerprint', $fingerprint)->first();

        if ($existing) {
            $aggregateUpdated = self::syncVoucherWeight($existing, $stampCount);
            $deviceUpdated = self::syncDeviceVoucherWeight($existing, $deviceCount);

            return response()->json([
                'success' => true,
                'applied' => true,
                'updated' => $aggregateUpdated || $deviceUpdated,
                'weight' => (int) $existing->weight,
                'deviceWeight' => (int) ($existing->device_weight ?? $existing->weight),
                'stampCount' => (int) $existing->stamp_count,
                'deviceStampCount' => (int) ($existing->device_stamp_count ?? 0),
                'appliedAt' => $existing->applied_at ? $existing->applied_at->toIso8601String() : null,
                'message' => 'すでに応募済みです',
            ]);
        }

        if ($deviceCount < self::voucherThreshold()) {
            return response()->json([
                'success' => false,
                'error' => 'not_eligible',
                'stampCount' => $stampCount,
                'deviceStampCount' => $deviceCount,
                'deviceDetectedCount' => self::detectedStampCountFor($fingerprint),
                'voucherThreshold' => self::voucherThreshold(),
                'message' => sprintf(
                    '抽選応募には1端末で%d種類の収集（ボールを当ててコレクションに入った数）が必要です（現在%d種）',
                    self::voucherThreshold(),
                    $deviceCount
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

        // メールアドレス確認（FR-61）: 確認有効時はコード一致を確認してから応募行を作る。
        // 未確認の応募行は作らない（業者提出 CSV に未確認行を混ぜない）。
        $emailCodeSentAt = null;
        if (self::emailVerificationEnabled()) {
            $check = self::consumeEmailCode($fingerprint, $email, $validated['code'] ?? null);

            if (!$check['ok']) {
                return response()->json([
                    'success' => false,
                    'error' => $check['error'],
                    'remainingAttempts' => $check['remaining_attempts'] ?? null,
                    'message' => $check['message'],
                ], 422);
            }

            $emailCodeSentAt = isset($check['sent_at'])
                ? Carbon::createFromTimestamp($check['sent_at'])
                : null;
        }

        try {
            $application = Stamp202611VoucherApplication::create([
                'session_id' => $sessionId,
                'fingerprint' => $fingerprint,
                'email' => $email,
                'name' => $name,
                'stamp_count' => $stampCount,
                'tier' => self::tierFor($stampCount),
                // 抽選列 weight は正本（1 端末単位）で決める（FR-56）
                'weight' => max(1, self::weightFor($deviceCount)),
                'device_stamp_count' => $deviceCount,
                'device_tier' => self::tierFor($deviceCount),
                'device_weight' => max(1, self::weightFor($deviceCount)),
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip(),
                'device_info' => $validated['deviceInfo'],
                'applied_at' => now(),
                // 確認コード送信日時（確認無効時は NULL）と確認完了日時（FR-61）
                'email_code_sent_at' => $emailCodeSentAt,
                'email_verified_at' => now(),
            ]);
        } catch (QueryException $e) {
            // SQLite のロックは UNIQUE 競合ではない（誤って「すでに応募済みです」にしない）
            if (self::isDatabaseLock($e)) {
                throw $e;
            }

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
            'deviceWeight' => (int) $application->device_weight,
            'stampCount' => (int) $application->stamp_count,
            'deviceStampCount' => (int) $application->device_stamp_count,
            'appliedAt' => $application->applied_at->toIso8601String(),
            'message' => '市内共通商品券の抽選会に応募しました',
        ]);
    }

    // ── 応募後のメールアドレス変更（FR-63 / design §5.8）───────────────────

    /**
     * 変更対象の応募行を取得する（checkStatus() と同じ単位: fingerprint 優先、無ければ session_id）。
     */
    private static function findApplicationForChange(string $fingerprint): ?Stamp202611VoucherApplication
    {
        $application = Stamp202611VoucherApplication::where('fingerprint', $fingerprint)->first();

        if (!$application) {
            $sessionId = session()->getId();

            if ($sessionId) {
                $application = Stamp202611VoucherApplication::where('session_id', $sessionId)->first();
            }
        }

        return $application;
    }

    /**
     * 変更用 確認コードの送信（POST /stamp202611/voucher-email-change-code・FR-63）。
     *
     * 応募時（requestEmailCode）と同じ順序で無駄なメールを防ぐ:
     * ①変更期限経過 → 422 change_deadline_passed ②応募行が無い → 422 not_applied
     * ③現登録と同じ → 422 same_email ④dns_check = true → 422 invalid_email
     * ⑤他の応募行が使うアドレス → 422 email_in_use ⑥enabled = false → 送信なしで変更可
     * ⑦resend_interval 以内の再送信 → 429 too_soon
     */
    public function requestEmailChangeCode(Request $request)
    {
        $options = self::emailVerificationOptions();

        // 'dns' は Laravel のバリデーションルールではない（emailDomainExists() で照会する・FR-61 の注記）
        $validated = $request->validate([
            'fingerprint' => 'required|string',
            'email' => 'required|email|max:190',
        ]);

        $fingerprint = $validated['fingerprint'];
        $email = strtolower(trim($validated['email']));

        session(['ar_fingerprint_202611' => $fingerprint]);

        if (now()->gt(self::emailChangeDeadline())) {
            return response()->json([
                'success' => false,
                'error' => 'change_deadline_passed',
                'emailChangeDeadline' => self::emailChangeDeadline()->toIso8601String(),
                'message' => '抽選の準備のため、メールアドレスは変更できません',
            ], 422);
        }

        $application = self::findApplicationForChange($fingerprint);

        if (!$application) {
            return response()->json([
                'success' => false,
                'error' => 'not_applied',
                'message' => '応募が見つかりません。ページを再読み込みしてください',
            ], 422);
        }

        if ($email === strtolower((string) $application->email)) {
            return response()->json([
                'success' => false,
                'error' => 'same_email',
                'message' => '現在登録済みのメールアドレスです。別のアドレスを入力してください',
            ], 422);
        }

        if ($options['dns_check'] && !self::emailDomainExists($email)) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_email',
                'message' => 'メールアドレスのドメインが見つかりません。入力をご確認ください',
            ], 422);
        }

        // 1 メールアドレス = 1 応募（FR-44）: 他の応募行が使うアドレスには変更できない
        if (Stamp202611VoucherApplication::where('email', $email)->where('id', '!=', $application->id)->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'email_in_use',
                'message' => 'そのメールアドレスはすでに別の応募で使われています',
            ], 422);
        }

        // 確認無効（開発環境・SMTP 未設定）: 送信なしで変更できる（changeVoucherEmail() も code を要求しない）
        if (!self::emailVerificationEnabled()) {
            return response()->json([
                'success' => true,
                'verification_required' => false,
                'email' => self::maskEmail($email),
            ]);
        }

        $key = self::emailCodeCacheKey($fingerprint, 'change');
        $current = Cache::get($key);

        // 応募時と同じ規則: 間隔を要求するのは「同じアドレスへの」再送信だけ。
        // 入力ミスを直して別のアドレスへ送り直す場合は待たせない（FR-63）。
        if (is_array($current) && $options['resend_interval'] > 0
            && ($current['email'] ?? '') === $email) {
            $elapsed = now()->getTimestamp() - (int) ($current['sent_at'] ?? 0);

            if ($elapsed >= 0 && $elapsed < $options['resend_interval']) {
                return response()->json([
                    'success' => false,
                    'error' => 'too_soon',
                    'waitSeconds' => $options['resend_interval'] - $elapsed,
                    'message' => '少し待ってからもう一度送信してください',
                ], 429);
            }
        }

        $code = (string) random_int(100000, 999999);
        $ttlMinutes = (int) ceil($options['code_ttl'] / 60);

        Cache::put($key, [
            'email' => $email,
            'code' => $code,
            'sent_at' => now()->getTimestamp(),
            'expires_at' => now()->getTimestamp() + $options['code_ttl'],
            'attempts' => 0,
        ], $options['code_ttl']);

        try {
            Mail::to($email)->send(new Stamp202611VoucherCodeMail($code, $ttlMinutes, 'change'));
        } catch (\Throwable $e) {
            Cache::forget($key);
            report($e);

            return response()->json([
                'success' => false,
                'error' => 'mail_failed',
                'message' => '確認メールを送信できませんでした。時間をおいてもう一度お試しください',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'verification_required' => true,
            'email' => self::maskEmail($email),
            'ttlMinutes' => $ttlMinutes,
            'resendInterval' => $options['resend_interval'],
            'message' => '新しいメールアドレスへ確認コードを送信しました',
        ]);
    }

    /**
     * 応募済みメールアドレスの変更確定（POST /stamp202611/voucher-email-change・FR-63）。
     *
     * 確認有効時は change 用の 6 桁コードが一致してから email 列を書き換える（未確認のまま変更しない）。
     * 変更後は email_code_sent_at（変更用コードの送信時刻）と email_verified_at（変更完了時刻）を更新し、
     * 管理画面 / CSV で「確認済み」のまま維持する。weight など抽選列は変更しない。
     */
    public function changeVoucherEmail(Request $request)
    {
        $validated = $request->validate([
            'fingerprint' => 'required|string',
            'email' => 'required|email|max:190',
            // 変更用コード（requestEmailChangeCode で送信した 6 桁）。確認無効時は省略可
            'code' => 'nullable|string|max:10',
        ]);

        $fingerprint = $validated['fingerprint'];
        $email = strtolower(trim($validated['email']));

        session(['ar_fingerprint_202611' => $fingerprint]);

        if (now()->gt(self::emailChangeDeadline())) {
            return response()->json([
                'success' => false,
                'error' => 'change_deadline_passed',
                'emailChangeDeadline' => self::emailChangeDeadline()->toIso8601String(),
                'message' => '抽選の準備のため、メールアドレスは変更できません',
            ], 422);
        }

        $application = self::findApplicationForChange($fingerprint);

        if (!$application) {
            return response()->json([
                'success' => false,
                'error' => 'not_applied',
                'message' => '応募が見つかりません。ページを再読み込みしてください',
            ], 422);
        }

        if ($email === strtolower((string) $application->email)) {
            return response()->json([
                'success' => false,
                'error' => 'same_email',
                'message' => '現在登録済みのメールアドレスです。別のアドレスを入力してください',
            ], 422);
        }

        // 1 メールアドレス = 1 応募（FR-44）
        if (Stamp202611VoucherApplication::where('email', $email)->where('id', '!=', $application->id)->exists()) {
            return response()->json([
                'success' => false,
                'error' => 'email_in_use',
                'message' => 'そのメールアドレスはすでに別の応募で使われています',
            ], 422);
        }

        $emailCodeSentAt = $application->email_code_sent_at;

        if (self::emailVerificationEnabled()) {
            $check = self::consumeEmailCode($fingerprint, $email, $validated['code'] ?? null, 'change');

            if (!$check['ok']) {
                return response()->json([
                    'success' => false,
                    'error' => $check['error'],
                    'remainingAttempts' => $check['remaining_attempts'] ?? null,
                    'message' => $check['message'],
                ], 422);
            }

            // 変更用コードの送信時刻を保持する（応募時の送信時刻は上書きする）
            $emailCodeSentAt = isset($check['sent_at'])
                ? Carbon::createFromTimestamp($check['sent_at'])
                : now();
        }

        try {
            $application->email = $email;
            $application->email_code_sent_at = $emailCodeSentAt;
            $application->email_verified_at = now();
            $application->save();
        } catch (QueryException $e) {
            // SQLite のロックは UNIQUE 競合ではない（refresh() もロックされるため即再スローする）
            if (self::isDatabaseLock($e)) {
                throw $e;
            }

            // email UNIQUE 競合（同時リクエスト）
            $application->refresh(); // 変更前の値へ戻す（後続の save() で未確認値を書かない）

            return response()->json([
                'success' => false,
                'error' => 'email_in_use',
                'message' => 'そのメールアドレスはすでに別の応募で使われています',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'email' => self::maskEmail($email),
            'emailVerified' => true,
            'changedAt' => $application->email_verified_at->toIso8601String(),
            'message' => 'メールアドレスを変更しました',
        ]);
    }
}
