<?php

namespace App\Console\Commands;

use App\Models\Stamp202611VoucherApplication;
use Illuminate\Console\Command;

/**
 * 既存応募行の 1 端末単位（device_*）列を marker_scans から埋め戻す（FR-57・T-15）。
 *
 * 挙動:
 *   - 合算列 stamp_count / tier は保持する（FR-57）。
 *   - device_stamp_count / device_tier は capture_type='ball_hit'（収集）基準で算出する（FR-58）。
 *   - weight は遡及降格しない。device_weight = max(現在の weight, weightFor(1端末収集種数)) とする。
 *   - 抽選列 weight は正本 device_weight と揃える（FR-56）。
 *   - 冪等: 再実行しても値は変わらない（dry-run で差分のみ確認可能）。
 *
 * モード:
 *   - 既定（FR-57 踏襲）: 保存済みの device_stamp_count より算出値が大きい行だけ更新する。
 *     小さい行（検出込みで水増しされていた行）はスキップして --recount-device-count へ委ねる。
 *   - --recount-device-count（FR-58）: device_stamp_count / device_tier を収集基準へ再集計し、
 *     下方更新も含めて書き換える。付与済み倍率（weight）は降格させない。
 *
 * 使い方:
 *   php artisan stamp202611:backfill-device-weight --dry-run
 *   php artisan stamp202611:backfill-device-weight --recount-device-count --dry-run
 *   php artisan stamp202611:backfill-device-weight --recount-device-count
 */
class BackfillStamp202611DeviceWeight extends Command
{
    protected $signature = 'stamp202611:backfill-device-weight
        {--dry-run : 変更内容を表示のみ（DB 更新なし）}
        {--recount-device-count : device_stamp_count / device_tier を ball_hit（収集）基準で再集計し下方更新も行う（weight は降格しない・FR-58）}';

    protected $description = '既存の抽選応募行へ 1 端末単位の収集種数（ball_hit 基準）と当選倍率を backfill する（FR-57 / FR-58）';

    public function handle(): int
    {
        $dryRun  = (bool) $this->option('dry-run');
        $recount = (bool) $this->option('recount-device-count');

        $total = 0;
        $changed = 0;
        $grandfathered = 0;
        $skippedDowngrade = 0;

        Stamp202611VoucherApplication::query()
            ->orderBy('id')
            ->chunk(100, function ($applications) use ($dryRun, $recount, &$total, &$changed, &$grandfathered, &$skippedDowngrade) {
                foreach ($applications as $application) {
                    $total++;

                    // 収集種数（capture_type='ball_hit'・FR-58）。検出のみの種数は含めない
                    $deviceCount = \App\Http\Controllers\StampRally202611Controller::deviceStampCountFor($application->fingerprint);
                    $storedCount = $application->device_stamp_count === null ? null : (int) $application->device_stamp_count;

                    // 既定モードは上方更新のみ（FR-57 踏襲）。水増し値の是正は --recount-device-count に委ねる
                    if (!$recount && $storedCount !== null && $deviceCount < $storedCount) {
                        $skippedDowngrade++;
                        continue;
                    }

                    $granted = max(1, (int) $application->weight);
                    $deviceWeight = max($granted, max(1, \App\Http\Controllers\StampRally202611Controller::weightFor($deviceCount)));
                    $deviceTier = \App\Http\Controllers\StampRally202611Controller::tierFor($deviceCount);

                    $isGrandfathered = $application->device_weight === null && $deviceWeight > \App\Http\Controllers\StampRally202611Controller::weightFor($deviceCount);
                    if ($isGrandfathered) {
                        $grandfathered++;
                    }

                    $differs = (int) $application->device_stamp_count !== $deviceCount
                        || (int) ($application->device_tier ?? -1) !== $deviceTier
                        || (int) ($application->device_weight ?? -1) !== $deviceWeight
                        || (int) $application->weight !== $deviceWeight;

                    if (!$differs) {
                        continue;
                    }

                    $changed++;

                    $this->line(sprintf(
                        'id=%d %s 合算%d種 → 1端末収集%d種（検出%d種） / weight %d → %d%s%s',
                        $application->id,
                        $application->email,
                        (int) $application->stamp_count,
                        $deviceCount,
                        \App\Http\Controllers\StampRally202611Controller::detectedStampCountFor($application->fingerprint),
                        $granted,
                        $deviceWeight,
                        $recount && $storedCount !== null && $deviceCount < $storedCount ? ' [recount]' : '',
                        $isGrandfathered ? ' [grandfathering]' : ''
                    ));

                    if ($dryRun) {
                        continue;
                    }

                    $application->device_stamp_count = $deviceCount;
                    $application->device_tier = $deviceTier;
                    $application->device_weight = $deviceWeight;
                    $application->weight = $deviceWeight;
                    $application->save();
                }
            });

        $this->info(sprintf(
            '%s%s: 対象 %d 件 / 更新 %d 件（grandfathering %d 件・下方更新スキップ %d 件）',
            $dryRun ? 'dry-run' : 'backfill',
            $recount ? ' / recount-device-count' : '',
            $total,
            $changed,
            $grandfathered,
            $skippedDowngrade
        ));

        return self::SUCCESS;
    }
}
