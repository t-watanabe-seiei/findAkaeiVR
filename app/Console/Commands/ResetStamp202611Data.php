<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * ARstampRally202611 のテストデータをサーバー側から消去する（FR-65 / design.md §5.11）。
 *
 * 挙動:
 *   - marker_scans を全行削除する（テーブルは 202610 以前と共有・キャンペーン区別列が無いため
 *     過去キャンペーンの履歴も消える。消したくない場合は本コマンドを使わず SQL で期間指定する）。
 *   - stamp202611_voucher_applications を全行削除する（fingerprint / email の UNIQUE 制約も解放）。
 *   - prize_exchanges には触らない（202611 では不使用・202609 / 202610 専用）。
 *   - cache を flush する（メール確認コード ar202611:voucher-email-code:* と
 *     RateLimiter の 1 時間窓を解放する。--keep-cache で省略可）。
 *   - sessions を削除する（session に保持される ar_fingerprint_202611 の紐付きを切る。
 *     --keep-sessions で省略可）。
 *   - SQLite では sqlite_sequence の該当行も消して id を 1 へ戻す。
 *
 * クライアント側（参加者の端末）の localStorage / IndexedDB はこのコマンドでは消えない。
 * AR202611_RESET_EPOCH を 1 増やして js-reset.blade.php に検出させる（design.md §5.11）。
 *
 * 使い方:
 *   php artisan stamp202611:reset --dry-run
 *   php artisan stamp202611:reset --force
 *   php artisan stamp202611:reset --force --keep-sessions --keep-cache
 */
class ResetStamp202611Data extends Command
{
    protected $signature = 'stamp202611:reset
        {--dry-run : 削除対象の件数を表示するだけで、何も削除しない}
        {--force : 確認なしで削除を実行する（dry-run 以外では必須）}
        {--keep-sessions : sessions テーブルを保持する}
        {--keep-cache : cache を flush しない}';

    protected $description = 'ARstampRally202611 のテストデータ（marker_scans / 抽選応募 / cache / sessions）をサーバー側から消去する';

    /** @var array<int, string> */
    private const TABLES = ['marker_scans', 'stamp202611_voucher_applications'];

    public function handle(): int
    {
        $dryRun        = (bool) $this->option('dry-run');
        $force         = (bool) $this->option('force');
        $keepSessions  = (bool) $this->option('keep-sessions');
        $keepCache     = (bool) $this->option('keep-cache');

        if (!$dryRun && !$force) {
            $this->error('削除するには --force を付けてください（まず --dry-run で件数を確認してください）。');

            return self::FAILURE;
        }

        $collectStart = (string) config('ar202611.collect_start');
        $epoch        = (int) config('ar202611.reset_epoch', 1);

        $scansTotal      = DB::table('marker_scans')->count();
        $scansInPeriod   = DB::table('marker_scans')->where('scanned_at', '>=', $collectStart)->count();
        $fingerprints    = DB::table('marker_scans')->whereNotNull('fingerprint')->distinct()->count('fingerprint');
        $applications    = DB::table('stamp202611_voucher_applications')->count();
        $sessions        = $keepSessions ? -1 : DB::table('sessions')->count();

        $this->info(sprintf(
            '削除対象: marker_scans %d 件（%s 以降 %d 件・fingerprint %d 件）/ 抽選応募 %d 件',
            $scansTotal,
            $collectStart,
            $scansInPeriod,
            $fingerprints,
            $applications
        ));
        $this->line(sprintf(
            'cache: %s / sessions: %s',
            $keepCache ? '保持' : 'flush する',
            $keepSessions ? '保持' : sprintf('%d 件削除', $sessions)
        ));
        $this->warn('marker_scans は 202610 以前と共有です。過去キャンペーンの履歴も削除されます。');

        // 世代番号は dry-run でも表示する（何も削除せずに .env の更新と
        // config:clear / view:clear / OPcache の反映を確認できるため）
        $this->line(sprintf('現在の AR202611_RESET_EPOCH = %d', $epoch));
        $this->line('端末側の localStorage / IndexedDB を消すには、この値を 1 増やして .env を更新し、');
        $this->line('php artisan config:clear && php artisan view:clear && php artisan cache:clear を実行してください（FR-65）。');

        if ($dryRun) {
            $this->info('dry-run: 何も削除していません。実行するには --force を付けてください。');

            return self::SUCCESS;
        }

        $deleted = ['marker_scans' => 0, 'stamp202611_voucher_applications' => 0, 'sessions' => 0];

        DB::transaction(function () use (&$deleted, $keepSessions, $keepCache) {
            $deleted['marker_scans'] = DB::table('marker_scans')->delete();
            $deleted['stamp202611_voucher_applications'] = DB::table('stamp202611_voucher_applications')->delete();

            if (!$keepSessions) {
                $deleted['sessions'] = DB::table('sessions')->delete();
            }

            $this->resetSqliteSequence();

            if (!$keepCache) {
                Cache::flush();
            }
        });

        $this->info(sprintf(
            '削除完了: marker_scans %d 件 / 抽選応募 %d 件 / sessions %d 件 / cache %s',
            $deleted['marker_scans'],
            $deleted['stamp202611_voucher_applications'],
            $deleted['sessions'],
            $keepCache ? '保持' : 'flush'
        ));

        return self::SUCCESS;
    }

    /**
     * SQLite の AUTOINCREMENT 連番を 1 へ戻す（他 DB では何もしない）。
     */
    private function resetSqliteSequence(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        $exists = DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'sqlite_sequence'");
        if ($exists === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count(self::TABLES), '?'));
        DB::statement('DELETE FROM sqlite_sequence WHERE name IN (' . $placeholders . ')', self::TABLES);
    }
}