<?php

/*
|--------------------------------------------------------------------------
| ARstampRally202611
|--------------------------------------------------------------------------
| アセット差し替え・閾値・収集期間・応募期限の単一切替点（design.md §3）。
| 202611 専用アセット（public/cg/202611/ の Model_00..20.glb と
| pattern-maker00..20.patt）へ切替済み（2026-11-04 / asset-swap-guide.md）。
| 202610 へ戻す場合も asset_dir と public/sw-stamp202611.js の
| ASSET_DIR を同期し、VERSION は必ず新しい値へ上げる（FR-36）。
*/

return [
    'asset_dir'    => env('AR202611_ASSET_DIR', '202611'),
    'marker_count' => (int) env('AR202611_MARKER_COUNT', 20),

    // お菓子のガラポン: 到達段階（各 1 回・最大 4 回）。回数はクライアント LocalStorage で管理（FR-37〜FR-40）
    'candy_tiers'  => [5, 10, 15, 20],

    // 市内共通商品券の抽選: 応募閾値と当選倍率（サーバー側で算出・強制・FR-41〜FR-44）
    'voucher_threshold' => (int) env('AR202611_VOUCHER_THRESHOLD', 10),
    'voucher_weights'   => [10 => 1, 15 => 2, 20 => 3],

    // ── Q-12 案A（2026-10-28 確定）: 収集期間と応募期限の分離（FR-50）──────
    // collect_*      : 収集種数（ガラポン段階・倍率）の算出 / marker_scans の集計 / scanned_at の妥当性検証
    // apply_deadline : apply-voucher が受理する最終日時（帰宅後に再送して応募できる猶予）
    // 確定値: 収集 2026-11-01 00:00:00 〜 2026-11-15 23:59:59 / 応募期限 2026-11-16 23:59:59（JST）
    // Q-9（2 日間開催）確定時はこの 3 値の書き換えのみで吸収する（design.md §10.1 に案B / 案C 手順を残置）
    'collect_start'  => env('AR202611_COLLECT_START', '2026-10-29 00:00:00'),
    'collect_end'    => env('AR202611_COLLECT_END', '2026-11-15 23:59:59'),
    'apply_deadline' => env('AR202611_APPLY_DEADLINE', '2026-11-16 23:59:59'),
    // ── メールアドレス確認（FR-61）────────────────────────────────────────
    // 抽選応募時に 6 桁の確認コードをメール送信して本人確認する。
    // enabled = false は確認を省略する開発用フォールバック（SMTP 未設定環境など）。
    // 送信元は config('mail.from.address')。本番（Xserver）は
    // MAIL_MAILER=smtp / MAIL_USERNAME=MAIL_FROM_ADDRESS=subscription@seiei.online を必須（design.md §5.7）。
    'email_verification' => [
        'enabled'         => env('AR202611_EMAIL_VERIFICATION', true),
        'code_ttl'        => (int) env('AR202611_EMAIL_CODE_TTL', 900),        // 確認コードの有効期限（秒・既定 15 分）
        'max_attempts'    => (int) env('AR202611_EMAIL_MAX_ATTEMPTS', 5),      // 1 コードあたりの入力試行上限
        'resend_interval' => (int) env('AR202611_EMAIL_RESEND_INTERVAL', 30),  // 再送信の間隔（秒）
        // 1 端末（fingerprint）あたり 1 時間のコード送信回数上限（throttle:stamp202611_email_code /
        // stamp202611_email_change_code）。アドレスの打ち間違いを直して送り直す再試行を吸収できる数を既定 6 にする。
        'code_send_limit_per_hour' => (int) env('AR202611_EMAIL_CODE_LIMIT_PER_HOUR', 6),
        // ドメイン存在チェック（true のときだけ emailDomainExists() で checkdnsrr() を呼ぶ）。
        // Laravel には `dns` バリデーションルールが無いので、ルール文字列へは連結しない（500 になる）。
        // 共有ホスティングでは DNS 参照が遅く誤検知もあり得るため既定は false（FR-61 の本人確認は 6 桁コードで成立する）。
        'dns_check'       => env('AR202611_EMAIL_DNS_CHECK', false),         // true で参加者のドメインを DNS 照会
    ],

    // ── 応募後のメールアドレス変更（FR-63）────────────────────────────────
    // 応募済み行のメールアドレスを本人確認付きで変更できる最終日時（JST）。
    // 抽選は 11 月末頃に行うため、それ以降は変更を受け付けない（既定 2026-11-30 23:59:59）。
    'email_change_deadline' => env('AR202611_EMAIL_CHANGE_DEADLINE', '2026-11-30 23:59:59'),

    // ── 複数端末での検証（FR-54）──────────────────────────────────────────
    // `?{test_param}=1` を付けた URL で参加すると fingerprint に test_prefix を接頭する。
    // 管理画面と業者提出 CSV は通常その計測を除外し、`?show_test=1` を付けたときだけ表示する
    // （design.md §3.4 / test-data-guide.md）
    'test_param'  => env('AR202611_TEST_PARAM', 'test'),
    'test_prefix' => env('AR202611_TEST_FP_PREFIX', 'test_'),

    // ── テストデータリセット（FR-65 / design.md §5.11）──────────────────────
    // サーバー側の marker_scans / stamp202611_voucher_applications を消した日と同じ日に
    // この値を 1 増やすと、次回のページ読み込みで全端末の 202611 系クライアント状態
    // （localStorage・IndexedDB・Service Worker キャッシュ）を自動で消去する。
    // 端末に保存された世代と不一致のときだけ発火するため冪等（毎回消えることはない）。
    // js-reset.blade.php が読み取り、stamp202611:reset コマンドが現在値を表示する。
    'reset_epoch' => (int) env('AR202611_RESET_EPOCH', 1),

];
