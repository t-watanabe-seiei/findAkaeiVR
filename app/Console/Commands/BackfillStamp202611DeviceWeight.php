<?php

namespace App\Console\Commands;

use App\Models\Stamp202611VoucherApplication;
use Illuminate\Console\Command;

/**
 * 既存応募行の 1 端末単位（device_*）列を marker_scans から埋め戻す（FR-57・T-15）。
 *
 * 挙動:
 *   - 合算列 stamp_count / tier は保持する（FR-57）。
 *   - weight は遡及降格しない。device_weight = max(現在の weight, weightFor(1端末種数)) とする。
 *   - 抽選列 weight は正本 device_weight と揃える（FR-56）。
 *   - 冪等: 再実行しても値は変わらない（dry-run で差分のみ確認可能）。
 *
 * 使い方:
 *   php artisan stamp202611:backfill-device-weight --dry-run
 *   php artisan stamp202611:backfill-device-weight
 */
class BackfillStamp202611DeviceWeight extends Command
{
    protected $signature = 'stamp202611:backfill-device-weight {--dry-run : 変更内容を表示のみ（DB 更新なし）}';

    protected $description = '既存の抽選応募行へ 1 端末単位の収集種数と当選倍率を backfill する（FR-57）';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $total = 0;
        $changed = 0;
        $grandfathered = 0;

        Stamp202611VoucherApplication::query()
            ->orderBy('id')
            ->chunk(100, function ($applications) use ($dryRun, &$total, &$changed, &$grandfathered) {
                foreach ($applications as $application) {
                    $total++;

                    $deviceCount = \App\Http\Controllers\StampRally202611Controller::deviceStampCountFor($application->fingerprint);
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
                        'id=%d %s 合算%d種 → 1端末%d種 / weight %d → %d%s',
                        $application->id,
                        $application->email,
                        (int) $application->stamp_count,
                        $deviceCount,
                        $granted,
                        $deviceWeight,
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
            '%s: 対象 %d 件 / 更新 %d 件（grandfathering %d 件）',
            $dryRun ? 'dry-run' : 'backfill',
            $total,
            $changed,
            $grandfathered
        ));

        return self::SUCCESS;
    }
}
