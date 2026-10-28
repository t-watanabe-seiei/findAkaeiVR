<?php

namespace Tests\Feature;

use App\Models\MarkerScan;
use App\Models\Stamp202611VoucherApplication;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
     * 収集期間内の日付に N 種のマーカー収集記録を作る（JST 壁時計で保存される）。
     */
    private function seedScans(string $fingerprint, int $kinds, string $jst = '2026-11-10 12:00:00'): void
    {
        for ($i = 1; $i <= $kinds; $i++) {
            MarkerScan::create([
                'session_id' => 'seed-' . $fingerprint,
                'fingerprint' => $fingerprint,
                'marker_id' => 'model_' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'marker_name' => 'model_' . $i,
                'scan_count' => 1,
                'capture_type' => 'marker_scan',
                'scanned_at' => Carbon::parse($jst, 'Asia/Tokyo'),
                'user_agent' => 'phpunit',
                'ip_address' => '127.0.0.1',
                'device_info' => ['isIOS' => false],
            ]);
        }
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

        // asset_dir 既定値（202610）のアセットを参照する（AC-2）
        $this->assertStringContainsString('cg/202610/pattern-maker01.patt', $html);
        $this->assertStringContainsString('cg/202610/Model_20.glb', $html);
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
        // 収集期間と応募期限の併記（Q-12 案A）
        $this->assertStringContainsString('2026/11/01 00:00', $html);
        $this->assertStringContainsString('2026/11/15 23:59', $html);
        $this->assertStringContainsString('2026/11/16 23:59', $html);
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
}


