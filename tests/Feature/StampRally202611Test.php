<?php

namespace Tests\Feature;

use App\Http\Controllers\StampRally202611Controller;
use App\Mail\Stamp202611VoucherCodeMail;
use App\Models\MarkerScan;
use App\Models\Stamp202611VoucherApplication;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

/**
 * ARstampRally202611 サーバー側検証（design.md §5 / requirements.md §11）。
 *
 * 実行（database/database.sqlite を壊さないよう必ずインメモリで実行する）:
 *   DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --filter=StampRally202611Test
 */
class StampRally202611Test extends TestCase
{
    use RefreshDatabase;

    /**
     * 既定ではメールアドレス確認（FR-61）を無効にする。
     * 確認導入以前の応募フローを検証する既存テストをそのまま通すためで、
     * 確認フロー自体のテストは enableEmailVerification() で有効化する。
     */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ar202611.email_verification.enabled' => false,
            'ar202611.email_verification.dns_check' => false,
        ]);
    }

    /**
     * メールアドレス確認を有効化する（既定で dns_check はオフ = テストで DNS 参照しない）。
     * $overrides は config/ar202611 の email_verification 配下のキー名で上書きする。
     *
     * @param array<string, mixed> $overrides 例: ['max_attempts' => 2, 'resend_interval' => 60]
     */
    private function enableEmailVerification(array $overrides = []): void
    {
        $values = array_merge([
            'enabled' => true,
            'dns_check' => false,
            'code_ttl' => 900,
            'max_attempts' => 5,
            'resend_interval' => 30,
        ], $overrides);

        foreach ($values as $key => $value) {
            config(['ar202611.email_verification.' . $key => $value]);
        }
    }

    /**
     * サーバーが Cache に保持している確認コードを取り出す（テスト内のコード一致確認用）。
     *
     * @return array{email:string,code:string,sent_at:int,expires_at:int,attempts:int}|null
     */
    private function cachedEmailCode(string $fingerprint): ?array
    {
        $entry = Cache::get('ar202611:voucher-email-code:' . $fingerprint);

        return is_array($entry) ? $entry : null;
    }

    /** 確認コード送信 API（FR-61） */
    private function requestEmailCode(string $fingerprint, string $email)
    {
        return $this->postJson('/stamp202611/voucher-email-code', [
            'fingerprint' => $fingerprint,
            'email' => $email,
        ]);
    }

    /** 確認コード付きの応募（FR-61） */
    private function applyVoucherWithCode(string $fingerprint, string $email, string $code)
    {
        return $this->postJson('/stamp202611/apply-voucher', [
            'fingerprint' => $fingerprint,
            'deviceInfo' => ['isIOS' => false],
            'email' => $email,
            'name' => 'テスト 太郎',
            'code' => $code,
        ]);
    }

    /**
     * 収集期間内の日付に N 種のマーカー収集記録を作る（JST 壁時計で保存される）。
     * 既定の capture_type は 'ball_hit'（ボールを当てて収集）。応募資格の正本はこれ（FR-58）。
     */
    private function seedScans(
        string $fingerprint,
        int $kinds,
        string $jst = '2026-11-10 12:00:00',
        string $prefix = 'model',
        string $captureType = 'ball_hit'
    ): void {
        $this->seedScansUnderSession($fingerprint, $kinds, 'seed-' . $fingerprint, $jst, $prefix, $captureType);
    }

    /**
     * マーカーを検出だけした記録（capture_type='marker_scan'）を作る。
     * 応募資格・倍率には使わない（FR-58）。表示・監査用の参考値の確認に使う。
     */
    private function seedDetections(
        string $fingerprint,
        int $kinds,
        string $jst = '2026-11-10 12:00:00',
        string $prefix = 'model'
    ): void {
        $this->seedScansUnderSession($fingerprint, $kinds, 'seed-' . $fingerprint, $jst, $prefix, 'marker_scan');
    }

    /**
     * session_id を指定して収集記録を作る（同一ブラウザ＝別端末の合算を再現する・FR-56）。
     * prefix を変えると別端末が別のマーカー種を収集した状況を再現できる。
     */
    private function seedScansUnderSession(
        string $fingerprint,
        int $kinds,
        string $sessionId,
        string $jst = '2026-11-10 12:00:00',
        string $prefix = 'model',
        string $captureType = 'ball_hit'
    ): void {
        for ($i = 1; $i <= $kinds; $i++) {
            $marker = $prefix . '_' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);

            MarkerScan::create([
                'session_id' => $sessionId,
                'fingerprint' => $fingerprint,
                'marker_id' => $marker,
                'marker_name' => $marker,
                'scan_count' => 1,
                'capture_type' => $captureType,
                'scanned_at' => Carbon::parse($jst, 'Asia/Tokyo'),
                'user_agent' => 'phpunit',
                'ip_address' => '127.0.0.1',
                'device_info' => ['isIOS' => false],
            ]);
        }
    }

    /**
     * 応募行（legacy 相当: device_* は NULL）を作る。運用中の行の再現に使う。
     */
    private function seedLegacyApplication(
        string $fingerprint,
        string $email,
        int $stampCount,
        int $weight,
        string $sessionId = 'sess-legacy'
    ): Stamp202611VoucherApplication {
        return Stamp202611VoucherApplication::create([
            'session_id' => $sessionId,
            'fingerprint' => $fingerprint,
            'email' => $email,
            'name' => 'Legacy 太郎',
            'stamp_count' => $stampCount,
            'tier' => StampRally202611Controller::tierFor($stampCount),
            'weight' => $weight,
            'user_agent' => 'phpunit',
            'ip_address' => '127.0.0.1',
            'device_info' => ['isIOS' => false],
            'applied_at' => Carbon::parse('2026-11-16 10:00:00', 'Asia/Tokyo'),
        ]);
    }

    private function checkStatus(string $fingerprint)
    {
        return $this->postJson('/stamp202611/check-status', ['fingerprint' => $fingerprint]);
    }

    private function applyVoucher(string $fingerprint, string $email)
    {
        return $this->postJson('/stamp202611/apply-voucher', [
            'fingerprint' => $fingerprint,
            'deviceInfo' => ['isIOS' => false],
            'email' => $email,
            'name' => 'テスト 太郎',
        ]);
    }

    public function test_index_renders_with_asset_dir_from_config(): void
    {
        $response = $this->get('/stamp202611');

        $response->assertStatus(200);
        $html = $response->getContent();

        // asset_dir 既定値（202611 専用アセット）を参照する（AC-2 / FR-36）
        $this->assertStringContainsString('cg/202611/pattern-maker01.patt', $html);
        $this->assertStringContainsString('cg/202611/Model_20.glb', $html);
        // 202611 側の動物名が反映されている（STAMPS[].name と dashboard の $animals が同一）
        $this->assertStringContainsString("name: 'ぶた①'", $html);
        $this->assertStringContainsString("name: 'バーガー'", $html);
        // 202610 からの分離（AC-3）: 旧サフィックス -r2 を含まない
        $this->assertStringNotContainsString('202610-r2', $html);
        $this->assertStringContainsString('ar-stamp-rally-202611', $html);
    }

    public function test_offline_and_csrf_routes(): void
    {
        $this->get('/stamp202611/offline')
            ->assertStatus(200)
            ->assertHeader('X-Robots-Tag', 'noindex');

        $this->get('/stamp202611/csrf')
            ->assertStatus(200)
            ->assertJsonStructure(['token']);
    }

    public function test_record_scan_keeps_client_scanned_at_within_collection_period(): void
    {
        // 帰宅後（11/16）に、収集日（11/10）の記録を再送するシナリオ（FR-49 / FR-51）
        $this->travelTo(Carbon::parse('2026-11-16 10:00:00', 'Asia/Tokyo'));

        $this->postJson('/stamp202611/record-scan', [
            'markerId' => 'model_01',
            'markerName' => 'model_01',
            'fingerprint' => 'fp-adopt',
            'deviceInfo' => ['isIOS' => true],
            'scannedAt' => '2026-11-10T02:00:00.000Z', // 11/10 11:00 JST
            'captureType' => 'marker_scan',
        ])->assertStatus(200)->assertJson(['success' => true]);

        $row = MarkerScan::latest('id')->first();

        // scanned_at は収集日（JST 壁時計）のまま。再送日時（11/16）へずれない
        $this->assertSame('2026-11-10 11:00:00', $row->scanned_at->toDateTimeString());
    }

    public function test_record_scan_falls_back_when_scanned_at_is_outside_period(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 10:00:00', 'Asia/Tokyo'));

        $this->postJson('/stamp202611/record-scan', [
            'markerId' => 'model_02',
            'markerName' => 'model_02',
            'fingerprint' => 'fp-fallback',
            'deviceInfo' => ['isIOS' => false],
            'scannedAt' => '2026-10-01T00:00:00.000Z', // 収集期間外
            'captureType' => 'marker_scan',
        ])->assertStatus(200);

        $row = MarkerScan::latest('id')->first();

        $this->assertSame(now()->toDateTimeString(), $row->scanned_at->toDateTimeString());
    }

    public function test_daily_duplicate_uses_adopted_scanned_at_date(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 10:00:00', 'Asia/Tokyo'));

        $payload = function (string $at) {
            return [
                'markerId' => 'model_03',
                'markerName' => 'model_03',
                'fingerprint' => 'fp-dup',
                'deviceInfo' => ['isIOS' => false],
                'scannedAt' => $at,
                'captureType' => 'marker_scan',
            ];
        };

        // 11/10 と 11/11 は別日として両方記録
        $this->postJson('/stamp202611/record-scan', $payload('2026-11-10T02:00:00.000Z'))->assertStatus(200);
        $this->postJson('/stamp202611/record-scan', $payload('2026-11-11T02:00:00.000Z'))->assertStatus(200);

        // 同じ 11/10 の再送は duplicate
        $this->postJson('/stamp202611/record-scan', $payload('2026-11-10T05:00:00.000Z'))
            ->assertStatus(200)
            ->assertJson(['success' => false, 'duplicate' => true]);

        $this->assertSame(2, MarkerScan::where('fingerprint', 'fp-dup')->count());
    }

    public function test_apply_voucher_requires_threshold(): void
    {
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00', 'Asia/Tokyo'));
        $this->seedScans('fp-9', 9);

        $this->applyVoucher('fp-9', 'nine@example.com')
            ->assertStatus(422)
            ->assertJson(['success' => false, 'error' => 'not_eligible', 'stampCount' => 9]);
    }

    public function test_apply_voucher_weights_by_kind_count(): void
    {
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00', 'Asia/Tokyo'));

        $this->seedScans('fp-10', 10);
        $this->applyVoucher('fp-10', 'ten@example.com')
            ->assertStatus(200)
            ->assertJson(['success' => true, 'applied' => true, 'weight' => 1, 'stampCount' => 10]);

        $this->seedScans('fp-15', 15);
        $this->applyVoucher('fp-15', 'fifteen@example.com')
            ->assertStatus(200)
            ->assertJson(['weight' => 2, 'stampCount' => 15]);

        $this->seedScans('fp-20', 20);
        $this->applyVoucher('fp-20', 'twenty@example.com')
            ->assertStatus(200)
            ->assertJson(['weight' => 3, 'stampCount' => 20]);

        // tier / weight が保存されている
        $row = Stamp202611VoucherApplication::where('fingerprint', 'fp-20')->first();
        $this->assertSame(20, $row->tier);
        $this->assertSame(3, $row->weight);
    }

    public function test_apply_after_collect_end_but_before_apply_deadline_succeeds(): void
    {
        // 収集は 11/10、応募は collect_end（11/15）経過後の 11/16（FR-49）
        $this->seedScans('fp-home', 10, '2026-11-10 12:00:00');
        $this->travelTo(Carbon::parse('2026-11-16 10:00:00', 'Asia/Tokyo'));

        $this->applyVoucher('fp-home', 'home@example.com')
            ->assertStatus(200)
            ->assertJson(['success' => true, 'weight' => 1, 'stampCount' => 10]);
    }

    public function test_apply_after_apply_deadline_is_rejected(): void
    {
        $this->seedScans('fp-late', 20, '2026-11-10 12:00:00');
        $this->travelTo(Carbon::parse('2026-11-17 00:00:01', 'Asia/Tokyo'));

        $this->applyVoucher('fp-late', 'late@example.com')
            ->assertStatus(422)
            ->assertJson(['success' => false, 'error' => 'deadline_passed']);

        $this->assertSame(0, Stamp202611VoucherApplication::where('fingerprint', 'fp-late')->count());
    }

    public function test_check_status_upgrades_weight_without_reapply(): void
    {
        $this->travelTo(Carbon::parse('2026-11-12 10:00:00', 'Asia/Tokyo'));
        $this->seedScans('fp-up', 10, '2026-11-10 12:00:00');

        $this->applyVoucher('fp-up', 'up@example.com')
            ->assertStatus(200)
            ->assertJson(['weight' => 1]);

        // 15 種へ増加 → 応募ボタンを再押下せずページ読み込み（check-status）だけで昇格（FR-43）
        $this->seedScans('fp-up', 15, '2026-11-12 09:00:00');

        $this->postJson('/stamp202611/check-status', ['fingerprint' => 'fp-up'])
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'stampCount' => 15,
                'candyTiers' => [5, 10, 15, 20],
                'voucher' => ['applied' => true, 'weight' => 2, 'stampCount' => 15, 'updated' => true],
            ]);

        // 2 回目（種数変化なし）は updated: false
        $this->postJson('/stamp202611/check-status', ['fingerprint' => 'fp-up'])
            ->assertStatus(200)
            ->assertJson(['voucher' => ['weight' => 2, 'updated' => false]]);
    }

    public function test_check_status_never_downgrades_weight(): void
    {
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00', 'Asia/Tokyo'));

        // 20 種で応募済み（weight 3）の行に対し、種数が 15 のまま check-status する
        $this->seedScans('fp-down', 15, '2026-11-10 12:00:00');
        $this->applyVoucher('fp-down', 'down@example.com')->assertStatus(200);

        $application = Stamp202611VoucherApplication::where('fingerprint', 'fp-down')->first();
        $application->stamp_count = 20;
        $application->tier = 20;
        $application->weight = 3;
        $application->save();

        $this->postJson('/stamp202611/check-status', ['fingerprint' => 'fp-down'])
            ->assertStatus(200)
            ->assertJson(['stampCount' => 15, 'voucher' => ['weight' => 3, 'stampCount' => 20, 'updated' => false]]);
    }

    public function test_email_is_normalized_and_unique_per_person(): void
    {
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00', 'Asia/Tokyo'));
        $this->seedScans('fp-mail', 10);

        // 大文字混じりの email は trim + strtolower で保存される（FR-44）
        $this->postJson('/stamp202611/apply-voucher', [
            'fingerprint' => 'fp-mail',
            'deviceInfo' => ['isIOS' => false],
            'email' => '  User@Example.COM ',
            'name' => 'テスト 太郎',
        ])->assertStatus(200);

        $this->assertDatabaseHas('stamp202611_voucher_applications', ['email' => 'user@example.com']);

        // 別 fingerprint（別端末）で同一 email → 422 already_applied
        $this->seedScans('fp-mail2', 12);
        $this->applyVoucher('fp-mail2', 'user@example.com')
            ->assertStatus(422)
            ->assertJson(['success' => false, 'error' => 'already_applied']);

        // 同一 fingerprint の再応募は倍率の上方更新のみ（FR-43）
        $this->applyVoucher('fp-mail', 'user@example.com')
            ->assertStatus(200)
            ->assertJson(['success' => true, 'applied' => true, 'weight' => 1]);

        $this->assertSame(1, Stamp202611VoucherApplication::count());
    }

    public function test_prize_exchanges_table_is_untouched(): void
    {
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00', 'Asia/Tokyo'));
        $this->seedScans('fp-noexchange', 20);

        $this->applyVoucher('fp-noexchange', 'noexchange@example.com')->assertStatus(200);

        // 景品コードは発行しない（FR-8 / AC-11）
        $this->assertSame(0, \App\Models\PrizeExchange::count());
    }

    public function test_admin_dashboard_shows_collect_period_and_voucher_stats(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 10:00:00', 'Asia/Tokyo'));

        // 未ログインは管理者ログインへリダイレクト
        $this->get('/admin/dashboard202611')->assertRedirect('/admin/login');

        // 収集期間内の収集（15 種）と応募
        $this->seedScans('fp-ad', 15, '2026-11-10 12:00:00');
        $this->applyVoucher('fp-ad', 'ad@example.com')->assertStatus(200);

        // 収集期間外（11/17）のスキャンは収集統計に含めない（FR-50）
        $this->seedScans('fp-out', 3, '2026-11-17 09:00:00');

        $response = $this->withSession(['admin_authenticated' => true])
            ->get('/admin/dashboard202611');

        $response->assertStatus(200)
            ->assertViewIs('admin.dashboard202611')
            ->assertViewHas('totalScans', 15)
            ->assertViewHas('uniqueScanners', 1)
            ->assertViewHas('voucherTotal', 1)
            ->assertViewHas('voucherByWeight');

        // 応募は applied_at / スキャンは scanned_at の別枠集計（FR-50）
        $this->assertSame([2 => 1], $response->viewData('voucherByWeight')->toArray());

        $html = $response->getContent();
        $this->assertStringContainsString('抽選応募一覧（市内共通商品券）', $html);
        $this->assertStringContainsString('ad@example.com', $html);
        $this->assertStringContainsString('15種', $html);
        $this->assertStringContainsString('2倍', $html);
        // 収集期間と応募期限の併記（Q-12 案A・blade の表示は Y/m/d H:i / config 値へ追従させる）
        $period = StampRally202611Controller::collectPeriod();
        $this->assertStringContainsString(
            Carbon::parse((string) $period[0], 'Asia/Tokyo')->format('Y/m/d H:i'),
            $html
        );
        $this->assertStringContainsString(
            Carbon::parse((string) $period[1], 'Asia/Tokyo')->format('Y/m/d H:i'),
            $html
        );
        $this->assertStringContainsString(
            Carbon::parse((string) config('ar202611.apply_deadline'), 'Asia/Tokyo')->format('Y/m/d H:i'),
            $html
        );
        // 景品コードの redeem UI は残さない
        $this->assertStringNotContainsString('redeemPrize(', $html);
    }

    public function test_admin_csv_export_lists_voucher_applications_by_weight(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 12:00:00', 'Asia/Tokyo'));

        $this->seedScans('fp-c1', 10, '2026-11-10 12:00:00');
        $this->applyVoucher('fp-c1', 'c1@example.com')->assertStatus(200);

        $this->seedScans('fp-c2', 20, '2026-11-11 12:00:00');
        $this->applyVoucher('fp-c2', 'c2@example.com')->assertStatus(200);

        $response = $this->withSession(['admin_authenticated' => true])
            ->get('/admin/export?dataset=voucherApplications202611');

        $response->assertStatus(200);
        $csv = $response->streamedContent();

        // design §5.6 のヘッダ（name / email / stamp_count / weight / applied_at）
        $this->assertStringContainsString('name,email,stamp_count,weight,applied_at', $csv);
        $this->assertStringContainsString('c1@example.com', $csv);
        $this->assertStringContainsString('c2@example.com', $csv);
        // 倍率順（weight DESC）→ 3 倍（c2）が先に並ぶ（FR-45）
        $this->assertLessThan(strpos($csv, 'c1@example.com'), strpos($csv, 'c2@example.com'));
    }

    /**
     * FR-54: 複数端末での検証（?test=1）の計測をダッシュボードと業者提出 CSV から除外する。
     */
    public function test_admin_dashboard_and_csv_exclude_test_fingerprints(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 12:00:00', 'Asia/Tokyo'));

        // 本番計測（10 種）
        $this->seedScans('fp-real', 10, '2026-11-10 12:00:00');
        $this->applyVoucher('fp-real', 'real@example.com')->assertStatus(200);

        // 検証端末（?test=1 → fingerprint が test_ 始まり・20 種）
        $this->seedScans('test_fp-device-b', 20, '2026-11-10 13:00:00');
        $this->applyVoucher('test_fp-device-b', 'device-b@example.com')->assertStatus(200);

        // 既定: 検証データを除外
        $response = $this->withSession(['admin_authenticated' => true])
            ->get('/admin/dashboard202611');

        $response->assertStatus(200)
            ->assertViewHas('totalScans', 10)
            ->assertViewHas('uniqueScanners', 1)
            ->assertViewHas('voucherTotal', 1)
            ->assertViewHas('showTest', false);

        $html = $response->getContent();
        $this->assertStringContainsString('real@example.com', $html);
        $this->assertStringNotContainsString('device-b@example.com', $html);

        // 業者提出用 CSV にも検証データを混ぜない
        $csv = $this->withSession(['admin_authenticated' => true])
            ->get('/admin/export?dataset=voucherApplications202611')
            ->streamedContent();
        $this->assertStringContainsString('real@example.com', $csv);
        $this->assertStringNotContainsString('device-b@example.com', $csv);

        // ?show_test=1 では検証データも含めて表示（検証用）
        $shown = $this->withSession(['admin_authenticated' => true])
            ->get('/admin/dashboard202611?show_test=1');

        $shown->assertStatus(200)
            ->assertViewHas('totalScans', 30)
            ->assertViewHas('uniqueScanners', 2)
            ->assertViewHas('voucherTotal', 2)
            ->assertViewHas('showTest', true);
        $this->assertStringContainsString('device-b@example.com', $shown->getContent());

        // 検索と併用しても show_test が維持される（検索フォームの hidden で引き継ぐ）
        $searched = $this->withSession(['admin_authenticated' => true])
            ->get('/admin/dashboard202611?show_test=1&q=device-b@example.com');
        $searched->assertStatus(200)->assertViewHas('showTest', true);
        $this->assertStringContainsString('device-b@example.com', $searched->getContent());
        $this->assertStringContainsString('name="show_test" value="1"', $searched->getContent());

        // 応募一覧のページネーションも show_test を引き継ぐ（blade の withQueryString）
        $applications = $shown->viewData('voucherApplications');
        $this->assertStringContainsString('show_test=1', $applications->url(2));

        $csvAll = $this->withSession(['admin_authenticated' => true])
            ->get('/admin/export?dataset=voucherApplications202611&show_test=1')
            ->streamedContent();
        $this->assertStringContainsString('device-b@example.com', $csvAll);
    }

    /**
     * FR-54: 参加 URL の ?test=1 で fingerprint 接頭辞をクライアントへ注入する。
     */
    public function test_prize_view_exposes_test_flag_and_prefix(): void
    {
        $html = $this->get('/stamp202611')->getContent();
        $this->assertStringContainsString('TEST_FP_PARAM', $html);
        $this->assertStringContainsString('TEST_FP_PREFIX', $html);
        $this->assertStringContainsString('"test_"', $html);
        $this->assertStringContainsString('isTestMode', $html);
    }

    /**
     * FR-59: 抽選の参考値行（検出種数・同一ブラウザ合算・注意文）は検証モード限定の表示。
     * 本番 URL（/stamp202611）では描画されず、?test=1 のページだけに表示する。
     * 案A（クライアント側）のため文言は JS 中に残るが、すべて VOUCHER_DIAGNOSTICS_202611
     * ゲート内侧で出力されることを検査する。
     */
    public function test_voucher_diagnostics_lines_are_gated_to_test_mode(): void
    {
        $gate    = 'VOUCHER_DIAGNOSTICS_202611';
        $phrases = [
            'この端末が見つけたマーカー',
            '同一ブラウザの合算',
            '見つけていますが、抽選にはボールを当てて収集した種数だけ使えます',
        ];

        foreach (['/stamp202611', '/stamp202611?test=1'] as $url) {
            $html = $this->get($url)->getContent();
            // ゲートは isTestMode()（fingerprint 前置字と同じ判定）から作る
            $this->assertStringContainsString($gate . ' = isTestMode();', $html);

            foreach ($phrases as $phrase) {
                $pos = strpos($html, $phrase);
                $this->assertNotFalse($pos, "{$phrase} が出力されていない（{$url}）");
                $window = substr($html, max(0, $pos - 300), 300);
                $this->assertStringContainsString(
                    $gate,
                    $window,
                    "{$phrase} が検証モードゲートで守られていない（{$url}）"
                );
            }
        }
    }


    /**
     * FR-56: 応募資格と当選倍率は 1 端末（fingerprint）単位で判定する。
     * 同一ブラウザ（同一 session_id）で 2 端末を使った場合、合算種数を別端末へ帰属させない。
     */
    public function test_voucher_eligibility_and_weight_are_per_device(): void
    {
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00', 'Asia/Tokyo'));

        // 端末 A（fp-dev-a）: 10 種 → 応募可・weight 1
        $this->seedScansUnderSession('fp-dev-a', 10, 'sess-browser-1');
        $this->applyVoucher('fp-dev-a', 'dev-a@example.com')
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'stampCount' => 10,
                'weight' => 1,
                'deviceStampCount' => 10,
            ]);

        // 同一ブラウザの別端末（fp-dev-b）: 5 種（合算 then 15 種）
        $this->seedScansUnderSession('fp-dev-b', 5, 'sess-browser-1', '2026-11-10 12:00:00', 'other');

        $this->assertSame(15, StampRally202611Controller::stampCountFor('sess-browser-1', 'fp-dev-b'));
        $this->assertSame(5, StampRally202611Controller::deviceStampCountFor('fp-dev-b'));

        // 応募資格は 1 端末種数で判定 → 合算 15 種でも不可
        $this->applyVoucher('fp-dev-b', 'dev-b@example.com')
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => 'not_eligible',
                'deviceStampCount' => 5,
            ]);

        $this->assertSame(0, Stamp202611VoucherApplication::where('fingerprint', 'fp-dev-b')->count());

        // 自端末で 10 種の端末は合算に依存せず応募可
        $this->seedScans('fp-dev-c', 10);
        $this->applyVoucher('fp-dev-c', 'dev-c@example.com')
            ->assertStatus(200)
            ->assertJson(['weight' => 1, 'deviceStampCount' => 10]);

        $row = Stamp202611VoucherApplication::where('fingerprint', 'fp-dev-c')->first();
        $this->assertSame(10, (int) $row->device_stamp_count);
        $this->assertSame(10, (int) $row->device_tier);
        $this->assertSame(1, (int) $row->device_weight);
        $this->assertSame(1, (int) $row->weight);
    }

    /**
     * FR-56: check-status の倍率昇格は 1 端末種数のみを使う（合算種数は参考列だけ更新）。
     */
    public function test_check_status_upgrades_weight_only_from_own_device_count(): void
    {
        $this->travelTo(Carbon::parse('2026-11-12 10:00:00', 'Asia/Tokyo'));

        $this->seedScansUnderSession('fp-up2', 10, 'sess-browser-2');
        $this->applyVoucher('fp-up2', 'up2@example.com')
            ->assertStatus(200)
            ->assertJson(['weight' => 1, 'deviceStampCount' => 10]);

        $application = Stamp202611VoucherApplication::where('fingerprint', 'fp-up2')->first();

        // 同一ブラウザの別端末が 5 種 → 合算 then 15 種 / 自端末 10 種
        $this->seedScansUnderSession('fp-share-b', 5, 'sess-browser-2', '2026-11-10 12:00:00', 'other');
        $this->assertSame(15, StampRally202611Controller::stampCountFor('sess-browser-2', 'fp-up2'));
        $this->assertSame(10, StampRally202611Controller::deviceStampCountFor('fp-up2'));

        // 合算 15 種を weight へ反映しない（device_* 設定済み）
        $this->assertTrue(StampRally202611Controller::syncVoucherWeight($application, 15));
        $this->assertSame(15, (int) $application->stamp_count);
        $this->assertSame(1, (int) $application->weight);
        $this->assertFalse(StampRally202611Controller::syncDeviceVoucherWeight($application, 10));
        $this->assertSame(1, (int) $application->weight);

        // check-status でも weight 1 のまま
        $this->checkStatus('fp-up2')->assertStatus(200)->assertJson([
            'deviceStampCount' => 10,
            'voucher' => ['applied' => true, 'weight' => 1, 'updated' => false, 'deviceStampCount' => 10],
        ]);
        $this->assertSame(1, (int) $application->fresh()->weight);

        // 自端末が 15 種になった時点で昇格
        $this->seedScans('fp-up2', 15, '2026-11-12 09:00:00');
        $this->checkStatus('fp-up2')->assertStatus(200)->assertJson([
            'stampCount' => 15,
            'deviceStampCount' => 15,
            'voucher' => ['weight' => 2, 'updated' => true, 'deviceStampCount' => 15],
        ]);

        $application->refresh();
        $this->assertSame(15, (int) $application->device_stamp_count);
        $this->assertSame(15, (int) $application->device_tier);
        $this->assertSame(2, (int) $application->device_weight);
        $this->assertSame(2, (int) $application->weight);
    }

    /**
     * FR-57: 付与済みの倍率は遡及降格しない（1 端末種数が減っても元 weight を維持）。
     */
    public function test_device_sync_never_downgrades_granted_weight(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 12:00:00', 'Asia/Tokyo'));

        // 合算 15 種で weight 3 付与済み・自端末は 5 種
        $application = $this->seedLegacyApplication('fp-no-down', 'no-down@example.com', 15, 3, 'sess-legacy-3');
        $this->seedScans('fp-no-down', 5);

        $deviceCount = StampRally202611Controller::deviceStampCountFor('fp-no-down');
        $this->assertSame(5, $deviceCount);

        $this->assertFalse(StampRally202611Controller::syncDeviceVoucherWeight($application, $deviceCount));
        $this->assertSame(3, (int) $application->weight);
        $this->assertSame(5, (int) $application->device_stamp_count);
        $this->assertSame(0, (int) $application->device_tier);
        $this->assertSame(3, (int) $application->device_weight);

        // device_* 未設定（legacy）行では合算列の昇格が weight を上げる（FR-43）
        $legacy = $this->seedLegacyApplication('fp-legacy-4', 'legacy4@example.com', 10, 1, 'sess-legacy-4');
        $this->assertTrue(StampRally202611Controller::syncVoucherWeight($legacy, 15));
        $this->assertSame(15, (int) $legacy->stamp_count);
        $this->assertSame(2, (int) $legacy->weight);
        $this->assertNull($legacy->device_weight);
    }

    /**
     * FR-57: backfill コマンドは device_* 列を埋めるが weight を遡及降格しない（冪等）。
     */
    public function test_backfill_command_fills_device_columns_without_downgrading_weight(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 12:00:00', 'Asia/Tokyo'));

        // 合算 15 種で weight 2 付与済み・自端末（fp-bf-a）10 種 + 同一ブラウザの別端末 5 種
        $application = $this->seedLegacyApplication('fp-bf-a', 'bf-a@example.com', 15, 2, 'sess-legacy');
        $other = $this->seedLegacyApplication('fp-bf-b', 'bf-b@example.com', 15, 2, 'sess-legacy');
        $this->seedScans('fp-bf-a', 10);
        $this->seedScansUnderSession('fp-bf-b', 5, 'sess-legacy', '2026-11-10 12:00:00', 'other');

        $this->artisan('stamp202611:backfill-device-weight', ['--dry-run' => true])
            ->assertExitCode(0);
        $this->assertSame(0, Stamp202611VoucherApplication::whereNotNull('device_stamp_count')->count());

        $this->artisan('stamp202611:backfill-device-weight')->assertExitCode(0);

        $application->refresh();
        $this->assertSame(10, (int) $application->device_stamp_count);
        $this->assertSame(10, (int) $application->device_tier);
        $this->assertSame(2, (int) $application->device_weight);
        $this->assertSame(2, (int) $application->weight);
        $this->assertSame(15, (int) $application->stamp_count);

        $other->refresh();
        $this->assertSame(5, (int) $other->device_stamp_count);
        $this->assertSame(0, (int) $other->device_tier);
        $this->assertSame(2, (int) $other->device_weight);
        $this->assertSame(2, (int) $other->weight);

        // 2 回目実行でも変化しない（冪等）
        $this->artisan('stamp202611:backfill-device-weight')->assertExitCode(0);
        $application->refresh();
        $this->assertSame(10, (int) $application->device_stamp_count);
        $this->assertSame(2, (int) $application->weight);
    }

    /**
     * FR-43: legacy 行（device_* NULL）は付与済み weight を維持し、device_* は 1 端末種数で埋まる。
     */
    public function test_check_status_keeps_grandfathered_weight(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 12:00:00', 'Asia/Tokyo'));

        $application = $this->seedLegacyApplication('fp-gf-a', 'gf-a@example.com', 15, 2, 'sess-legacy-2');
        $this->seedScans('fp-gf-a', 10);

        $this->checkStatus('fp-gf-a')->assertStatus(200)->assertJson([
            'stampCount' => 10,
            'deviceStampCount' => 10,
            'voucher' => ['applied' => true, 'weight' => 2, 'stampCount' => 15, 'updated' => false],
        ]);

        $application->refresh();
        $this->assertSame(2, (int) $application->weight);
        $this->assertSame(15, (int) $application->stamp_count);
        $this->assertSame(10, (int) $application->device_stamp_count);
        $this->assertSame(2, (int) $application->device_weight);

        // 同一ブラウザの別端末 5 種（合算 15 種）では昇格しない
        $this->seedScansUnderSession('fp-gf-b', 5, 'sess-legacy-2', '2026-11-10 12:00:00', 'other');
        $this->assertSame(15, StampRally202611Controller::stampCountFor('sess-legacy-2', 'fp-gf-a'));

        $this->checkStatus('fp-gf-a')->assertStatus(200)->assertJson([
            'voucher' => ['weight' => 2, 'updated' => false],
        ]);
        $this->assertSame(2, (int) $application->fresh()->weight);
    }

    /**
     * FR-57: ダッシュボードは合算種数（参考）と 1 端末種数（正本）を併記する。
     */
    public function test_admin_dashboard_lists_both_aggregate_and_device_counts(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 12:00:00', 'Asia/Tokyo'));

        // 合算 15 種で weight 2 付与済みの legacy 行（device_* NULL）
        $application = $this->seedLegacyApplication('fp-dash', 'dash@example.com', 15, 2, 'sess-dash');
        $this->seedScans('fp-dash', 10);
        $this->seedScansUnderSession('fp-dash-share', 5, 'sess-dash', '2026-11-10 12:00:00', 'other');

        $this->assertSame(15, StampRally202611Controller::stampCountFor('sess-dash', 'fp-dash'));

        $this->artisan('stamp202611:backfill-device-weight')->assertExitCode(0);

        $application->refresh();
        $this->assertSame(10, (int) $application->device_stamp_count);
        $this->assertSame(2, (int) $application->device_weight);

        $response = $this->withSession(['admin_authenticated' => true])
            ->get('/admin/dashboard202611');

        $response->assertStatus(200)
            ->assertViewIs('admin.dashboard202611')
            ->assertViewHas('voucherTotal', 1);

        $html = $response->getContent();
        $this->assertStringContainsString('1端末種数', $html);
        $this->assertStringContainsString('15種', $html);
        $this->assertStringContainsString('10種', $html);
        $this->assertStringContainsString('合算より 5種多い', $html);
    }

    /**
     * FR-58: マーカーを検出だけした記録（capture_type='marker_scan'）は応募資格に使わない。
     * 検出 11 種だけでも収集 0 種なら 422 not_eligible。
     */
    public function test_marker_scan_only_records_do_not_qualify_for_voucher(): void
    {
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00', 'Asia/Tokyo'));

        $this->seedDetections('fp-detect-only', 11);

        $this->assertSame(11, StampRally202611Controller::stampCountFor('seed-fp-detect-only', 'fp-detect-only'));
        $this->assertSame(0, StampRally202611Controller::deviceStampCountFor('fp-detect-only'));
        $this->assertSame(11, StampRally202611Controller::detectedStampCountFor('fp-detect-only'));

        $this->applyVoucher('fp-detect-only', 'detect-only@example.com')
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => 'not_eligible',
                'stampCount' => 11,
                'deviceStampCount' => 0,
                'deviceDetectedCount' => 11,
            ]);

        $this->assertSame(0, Stamp202611VoucherApplication::count());
    }

    /**
     * FR-58: check-status は収集種数（正本）と検出種数（参考）を分けて返す。
     */
    public function test_check_status_reports_collected_and_detected_counts(): void
    {
        $this->travelTo(Carbon::parse('2026-11-12 10:00:00', 'Asia/Tokyo'));

        // 収集 7 種（model_01〜07）・検出 11 種（model_01〜11）
        $this->seedScans('fp-mix', 7);
        $this->seedDetections('fp-mix', 11);

        $this->checkStatus('fp-mix')->assertStatus(200)->assertJson([
            'stampCount' => 11,
            'deviceStampCount' => 7,
            'deviceDetectedCount' => 11,
            'voucher' => ['applied' => false, 'weight' => 0, 'deviceStampCount' => 0],
        ]);
    }

    /**
     * FR-58: 応募可否と倍率は収集種数だけで決まる（検出種数は閾値へ影響しない）。
     */
    public function test_collected_count_at_threshold_qualifies_even_with_extra_detections(): void
    {
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00', 'Asia/Tokyo'));

        // 収集 10 種 + 検出 5 種（収集と同一種）→ 収集 10 種で応募可・weight 1
        $this->seedScans('fp-edge', 10);
        $this->seedDetections('fp-edge', 5);

        $this->applyVoucher('fp-edge', 'edge@example.com')
            ->assertStatus(200)
            ->assertJson(['success' => true, 'weight' => 1, 'deviceStampCount' => 10]);

        // 収集 9 種 + 検出 11 種 → 合算 11 種でも収集 9 種なので不可（2026-11-01 の水増しケース）
        $this->seedScans('fp-short', 9);
        $this->seedDetections('fp-short', 11);

        $this->assertSame(11, StampRally202611Controller::stampCountFor('seed-fp-short', 'fp-short'));
        $this->assertSame(9, StampRally202611Controller::deviceStampCountFor('fp-short'));

        $this->applyVoucher('fp-short', 'short@example.com')
            ->assertStatus(422)
            ->assertJson([
                'error' => 'not_eligible',
                'stampCount' => 11,
                'deviceStampCount' => 9,
                'deviceDetectedCount' => 11,
            ]);

        // 収集 15 種（検出 20 種）は weight 2 のまま（検出は倍率を上げない）
        $this->seedScans('fp-weight', 15);
        $this->seedDetections('fp-weight', 20);

        $this->applyVoucher('fp-weight', 'weight@example.com')
            ->assertStatus(200)
            ->assertJson(['weight' => 2, 'deviceStampCount' => 15, 'deviceWeight' => 2]);
    }

    /**
     * FR-58: --recount-device-count は検出込みで水増しした device_stamp_count を収集基準へ是正する。
     * 付与済みの weight / 合算列 stamp_count は降格させない。既定モードは下方更新しない。
     */
    public function test_backfill_recount_corrects_inflated_device_count_without_downgrading_weight(): void
    {
        $this->travelTo(Carbon::parse('2026-11-17 12:00:00', 'Asia/Tokyo'));

        // 検出込み 11 種で device_stamp_count = 11 / weight 2 が付与済みの行（2026-11-01 の水増し相当）
        $application = $this->seedLegacyApplication('fp-inflated', 'inflated@example.com', 11, 2, 'sess-inflated');
        $application->device_stamp_count = 11;
        $application->device_tier = 10;
        $application->device_weight = 2;
        $application->save();

        // 実際の収集は 7 種（model_01〜07）・検出は 11 種（model_01〜11）
        $this->seedScans('fp-inflated', 7);
        $this->seedDetections('fp-inflated', 11);

        // 既定モード（FR-57 踏襲）は下方更新しない
        $this->artisan('stamp202611:backfill-device-weight')->assertExitCode(0);
        $application->refresh();
        $this->assertSame(11, (int) $application->device_stamp_count);
        $this->assertSame(2, (int) $application->weight);

        // dry-run は DB を変更しない
        $this->artisan('stamp202611:backfill-device-weight --recount-device-count --dry-run')->assertExitCode(0);
        $application->refresh();
        $this->assertSame(11, (int) $application->device_stamp_count);

        // recount で収集基準へ是正（weight は降格しない）
        $this->artisan('stamp202611:backfill-device-weight --recount-device-count')->assertExitCode(0);
        $application->refresh();
        $this->assertSame(7, (int) $application->device_stamp_count);
        $this->assertSame(0, (int) $application->device_tier);
        $this->assertSame(2, (int) $application->device_weight);
        $this->assertSame(2, (int) $application->weight);
        $this->assertSame(11, (int) $application->stamp_count);

        // 冪等: 再実行しても変化しない
        $this->artisan('stamp202611:backfill-device-weight --recount-device-count')->assertExitCode(0);
        $application->refresh();
        $this->assertSame(7, (int) $application->device_stamp_count);
        $this->assertSame(2, (int) $application->weight);
    }

    // ── メールアドレス確認（FR-61 / design §5.7）─────────────────────────

    /**
     * FR-61: 確認コードはメール送信され Cache に保持される。応募行はこの時点で作らない。
     */
    public function test_email_code_is_sent_and_held_in_cache(): void
    {
        $this->enableEmailVerification();
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00', 'Asia/Tokyo'));

        $this->seedScans('fp-verify', 12);

        $this->requestEmailCode('fp-verify', 'Verify@Example.com')
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'verification_required' => true,
                'email' => 'v*****@example.com',
                'ttlMinutes' => 15,
            ]);

        Mail::assertSent(Stamp202611VoucherCodeMail::class, function (Stamp202611VoucherCodeMail $mail) {
            return $mail->hasTo('verify@example.com') && preg_match('/^\d{6}$/', $mail->code) === 1;
        });

        $entry = $this->cachedEmailCode('fp-verify');
        $this->assertIsArray($entry);
        $this->assertSame('verify@example.com', $entry['email']);
        $this->assertSame(0, (int) $entry['attempts']);
        $this->assertSame((int) $entry['sent_at'] + 900, (int) $entry['expires_at']);

        // コード送信だけでは応募行を作らない（未確認行を抽選 CSV に混ぜない）
        $this->assertSame(0, Stamp202611VoucherApplication::where('fingerprint', 'fp-verify')->count());
    }

    /**
     * FR-61: 確認コードが無い・一致しない応募は行を作らない。正しいコードで応募完了する。
     */
    public function test_apply_voucher_requires_valid_email_code(): void
    {
        $this->enableEmailVerification();
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00', 'Asia/Tokyo'));

        $this->seedScans('fp-code', 10);

        // コード未送信のままの応募は code_expired（Cache に entry が無い）
        $this->applyVoucherWithCode('fp-code', 'code@example.com', '123456')
            ->assertStatus(422)
            ->assertJson(['success' => false, 'error' => 'code_expired']);
        $this->assertSame(0, Stamp202611VoucherApplication::count());

        $this->requestEmailCode('fp-code', 'code@example.com')->assertStatus(200);
        $entry = $this->cachedEmailCode('fp-code');
        $this->assertIsArray($entry);

        // コード空 → code_required
        $this->applyVoucher('fp-code', 'code@example.com')
            ->assertStatus(422)
            ->assertJson(['success' => false, 'error' => 'code_required', 'remainingAttempts' => 5]);

        // 誤ったコード → code_invalid（残り回数が減る）
        $this->applyVoucherWithCode('fp-code', 'code@example.com', '000000')
            ->assertStatus(422)
            ->assertJson(['success' => false, 'error' => 'code_invalid', 'remainingAttempts' => 4]);
        $this->assertSame(0, Stamp202611VoucherApplication::count());

        // 正しいコード → 応募成功
        $this->applyVoucherWithCode('fp-code', 'code@example.com', (string) $entry['code'])
            ->assertStatus(200)
            ->assertJson(['success' => true, 'applied' => true, 'weight' => 1]);

        $application = Stamp202611VoucherApplication::where('fingerprint', 'fp-code')->firstOrFail();
        $this->assertTrue($application->isEmailVerified());
        $this->assertFalse($application->isLegacyEmailApplication());
        $this->assertNotNull($application->email_code_sent_at);
        $this->assertNotNull($application->email_verified_at);

        // 消費済みコードは Cache から消える（再利用できない）
        $this->assertNull($this->cachedEmailCode('fp-code'));
    }

    /**
     * FR-61: 誤入力が続く場合はコードを破棄し、新しい送信を要求する（総当たり対策）。
     */
    public function test_email_code_attempts_are_limited(): void
    {
        $this->enableEmailVerification(['max_attempts' => 2, 'resend_interval' => 0]);
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00', 'Asia/Tokyo'));

        $this->seedScans('fp-attempts', 12);
        $this->requestEmailCode('fp-attempts', 'attempts@example.com')->assertStatus(200);
        $entry = $this->cachedEmailCode('fp-attempts');
        $this->assertIsArray($entry);

        $this->applyVoucherWithCode('fp-attempts', 'attempts@example.com', '000000')
            ->assertStatus(422)
            ->assertJson(['error' => 'code_invalid', 'remainingAttempts' => 1]);

        $this->applyVoucherWithCode('fp-attempts', 'attempts@example.com', '000001')
            ->assertStatus(422)
            ->assertJson(['error' => 'code_invalid', 'remainingAttempts' => 0]);

        // 上限到達でコードは破棄済み → 正しいコードでも code_expired
        $this->assertNull($this->cachedEmailCode('fp-attempts'));
        $this->applyVoucherWithCode('fp-attempts', 'attempts@example.com', (string) $entry['code'])
            ->assertStatus(422)
            ->assertJson(['error' => 'code_expired']);

        $this->assertSame(0, Stamp202611VoucherApplication::count());
    }

    /**
     * FR-61: 再送信は resend_interval まで断る（送信コスト対策）。
     */
    public function test_email_code_resend_is_rate_limited(): void
    {
        $this->enableEmailVerification(['resend_interval' => 60]);
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00', 'Asia/Tokyo'));

        $this->seedScans('fp-resend', 12);

        $this->requestEmailCode('fp-resend', 'resend@example.com')->assertStatus(200);
        $first = $this->cachedEmailCode('fp-resend');

        $this->requestEmailCode('fp-resend', 'resend@example.com')
            ->assertStatus(429)
            ->assertJson(['success' => false, 'error' => 'too_soon', 'waitSeconds' => 60]);
        Mail::assertSentCount(1);

        // 間隔経過後は再送信できる（コードは作り直す）
        $this->travelTo(Carbon::parse('2026-11-14 10:01:01', 'Asia/Tokyo'));
        $this->requestEmailCode('fp-resend', 'resend@example.com')->assertStatus(200);
        Mail::assertSentCount(2);

        $second = $this->cachedEmailCode('fp-resend');
        $this->assertIsArray($second);
        $this->assertNotSame($first['code'], $second['code']);
    }

    /**
     * FR-61: 応募資格が無い端末にはコードを送らない（無駄なメールを送らない）。
     */
    public function test_request_email_code_rejects_ineligible_device(): void
    {
        $this->enableEmailVerification();
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00', 'Asia/Tokyo'));

        $this->seedScans('fp-poor', 9);

        $this->requestEmailCode('fp-poor', 'poor@example.com')
            ->assertStatus(422)
            ->assertJson(['success' => false, 'error' => 'not_eligible', 'deviceStampCount' => 9]);

        Mail::assertNothingSent();
        $this->assertNull($this->cachedEmailCode('fp-poor'));
    }

    /**
     * FR-61 / FR-44: 応募済みのメールアドレスにはコードを送らない。
     */
    public function test_request_email_code_rejects_email_already_applied(): void
    {
        $this->enableEmailVerification();
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00', 'Asia/Tokyo'));

        $this->seedScans('fp-a', 10);
        $this->seedScans('fp-b', 12);

        $this->requestEmailCode('fp-a', 'dup@example.com')->assertStatus(200);
        $entry = $this->cachedEmailCode('fp-a');
        $this->assertIsArray($entry);
        $this->applyVoucherWithCode('fp-a', 'dup@example.com', (string) $entry['code'])->assertStatus(200);

        $this->requestEmailCode('fp-b', 'DUP@example.com')
            ->assertStatus(422)
            ->assertJson(['success' => false, 'error' => 'already_applied']);
        $this->assertNull($this->cachedEmailCode('fp-b'));
    }

    /**
     * FR-61: check-status は確認状態を返す（未確認行を作らないため applied 時のみ true）。
     */
    public function test_check_status_reports_email_verification_state(): void
    {
        $this->enableEmailVerification();
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00', 'Asia/Tokyo'));

        $this->seedScans('fp-status', 15);

        $this->checkStatus('fp-status')
            ->assertStatus(200)
            ->assertJson(['voucher' => ['applied' => false, 'emailVerified' => false]]);
        $this->assertNull($this->cachedEmailCode('fp-status'));

        $this->requestEmailCode('fp-status', 'status@example.com')->assertStatus(200);
        $entry = $this->cachedEmailCode('fp-status');
        $this->assertIsArray($entry);
        $this->applyVoucherWithCode('fp-status', 'status@example.com', (string) $entry['code'])->assertStatus(200);

        $this->checkStatus('fp-status')
            ->assertStatus(200)
            ->assertJson(['voucher' => ['applied' => true, 'emailVerified' => true]]);
    }

    /**
     * FR-61 grandfathering: 確認導入以前の応募行は email_verified_at = applied_at で確認済み扱い。
     */
    public function test_migration_grandfathers_existing_applications(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 12:00:00', 'Asia/Tokyo'));

        // 確認導入前の状態に戻す（列を落とし、マイグレーション記録も消す）
        $this->seedLegacyApplication('fp-old', 'old@example.com', 15, 2);
        Schema::table('stamp202611_voucher_applications', function (Blueprint $table) {
            $table->dropColumn(['email_code_sent_at', 'email_verified_at']);
        });
        DB::table('migrations')
            ->where('migration', '2026_11_04_000000_add_email_verification_to_stamp202611_voucher_applications_table')
            ->delete();

        Artisan::call('migrate', [
            '--path' => base_path('database/migrations/2026_11_04_000000_add_email_verification_to_stamp202611_voucher_applications_table.php'),
            '--realpath' => true,
            '--force' => true,
        ]);

        $application = Stamp202611VoucherApplication::where('fingerprint', 'fp-old')->firstOrFail();
        $this->assertTrue($application->isEmailVerified());
        $this->assertTrue($application->isLegacyEmailApplication());
        $this->assertSame(
            $application->applied_at->toDateTimeString(),
            $application->email_verified_at->toDateTimeString()
        );
    }

    /**
     * FR-61: 確認コードメールの本文が描画される（Blade テンプレートの健全性チェック）。
     */
    public function test_email_code_mail_renders_code_and_ttl(): void
    {
        $html = (new Stamp202611VoucherCodeMail('123456', 15))->render();

        $this->assertStringContainsString('123456', $html);
        $this->assertStringContainsString('15 分', $html);
    }

    /**
     * FR-61 回帰テスト: dns_check = true でも 500 にならない。
     *
     * 本番で `BadMethodCallException: Method Illuminate\Validation\Validator::validateDns does not exist`
     * が発生した。Laravel には `dns` バリデーションルールが存在しないため、ルール文字列へ `|dns` を
     * 連結する実装は未定義ルール扱いで例外になる。DNS 照会は 200 か 422 invalid_email で返し、
     * 500 を出さないことを保証する。
     */
    public function test_email_code_request_with_dns_check_enabled_never_returns_500(): void
    {
        $this->enableEmailVerification(['dns_check' => true]);
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00', 'Asia/Tokyo'));

        $this->seedScans('fp-dns', 12);

        $response = $this->requestEmailCode('fp-dns', 'verify@example.com');

        // DNS 解決に成功すれば 200、できなければ 422 invalid_email（どちらでもよく、500 は出さない）
        $this->assertContains($response->getStatusCode(), [200, 422]);

        if ($response->getStatusCode() === 422) {
            $response->assertJson(['success' => false, 'error' => 'invalid_email']);
            Mail::assertNothingSent();
            $this->assertNull($this->cachedEmailCode('fp-dns'));

            return;
        }

        Mail::assertSent(Stamp202611VoucherCodeMail::class);
        $this->assertIsArray($this->cachedEmailCode('fp-dns'));
    }

    /**
     * FR-61: dns_check = true のとき、ドメインが解決できないアドレスは 422 invalid_email で弾く。
     * `.invalid` は RFC 2606 で解決しないことが保証されている予約 TLD（テストで DNS に依存しない）。
     */
    public function test_email_code_request_rejects_domain_without_dns_records(): void
    {
        $this->enableEmailVerification(['dns_check' => true]);
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00', 'Asia/Tokyo'));

        $this->seedScans('fp-nxdomain', 12);

        $this->requestEmailCode('fp-nxdomain', 'verify@example.invalid')
            ->assertStatus(422)
            ->assertJson(['success' => false, 'error' => 'invalid_email']);

        Mail::assertNothingSent();
        $this->assertNull($this->cachedEmailCode('fp-nxdomain'));
        $this->assertSame(0, Stamp202611VoucherApplication::count());
    }

    /**
     * FR-61: ドメイン照会ヘルパーの境界挙動（DNS 照会できない形は照会せず通過させる）。
     */
    public function test_email_domain_exists_helper_boundaries(): void
    {
        $method = new ReflectionMethod(StampRally202611Controller::class, 'emailDomainExists');
        $method->setAccessible(true);

        // 解決しない予約 TLD は false（422 invalid_email の根拠）
        $this->assertFalse($method->invoke(null, 'verify@example.invalid'));
        // @ が無い形は DNS 照会せず通過（email ルールで既に弾かれるため 500 の原因にしない）
        $this->assertTrue($method->invoke(null, 'nobody'));
    }
}

