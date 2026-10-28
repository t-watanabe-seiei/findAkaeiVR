<?php

/*
|--------------------------------------------------------------------------
| ARstampRally202611
|--------------------------------------------------------------------------
| アセット差し替え・閾値・収集期間・応募期限の単一切替点（design.md §3）。
| cg/202611 へ切り替える場合は asset_dir を変更し、
| public/sw-stamp202611.js の ASSET_DIR と VERSION を同期する（FR-36）。
*/

return [
    'asset_dir'    => env('AR202611_ASSET_DIR', '202610'),
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
    'collect_start'  => env('AR202611_COLLECT_START', '2026-11-01 00:00:00'),
    'collect_end'    => env('AR202611_COLLECT_END', '2026-11-15 23:59:59'),
    'apply_deadline' => env('AR202611_APPLY_DEADLINE', '2026-11-16 23:59:59'),
];
