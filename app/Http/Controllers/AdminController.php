<?php

namespace App\Http\Controllers;

use App\Models\MarkerScan;
use App\Models\PrizeExchange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminController extends Controller
{
    // ログインページ表示
    public function showLogin()
    {
        return view('admin.login');
    }

    // ログイン処理
    public function login(Request $request)
    {
        $username = $request->input('username');
        $password = $request->input('password');

        // 管理者認証（ハードコーディング）
        if ($username === 'admin' && $password === '0835385252') {
            session(['admin_authenticated' => true]);
            return redirect()->route('admin.dashboard');
        }

        return back()->withErrors(['login' => 'ユーザー名またはパスワードが正しくありません']);
    }

    // ログアウト
    public function logout()
    {
        session()->forget('admin_authenticated');
        return redirect()->route('admin.login');
    }

    // ダッシュボード
    public function dashboard(Request $request)
    {
        // 景品交換の統計
        $totalExchanges = PrizeExchange::count();
        $redeemedExchanges = PrizeExchange::where('is_redeemed', true)->count();
        $pendingExchanges = $totalExchanges - $redeemedExchanges;

        // 最近の景品交換（未使用のみ）- ページネーション
        // optional search by prize code (query param: q)
        $q = $request->query('q');
        $recentQuery = PrizeExchange::where('is_redeemed', false);
        if ($q) {
            // allow partial matches (case-insensitive)
            $recentQuery->where('prize_code', 'like', '%' . strtoupper($q) . '%');
        }
        $recentExchanges = $recentQuery->orderBy('exchanged_at', 'desc')
            ->paginate(20, ['*'], 'exchanges_page')->appends(['q' => $q]);

        // 使用済み景品交換 - ページネーション（10件ごと）
        $redeemedPrizes = PrizeExchange::where('is_redeemed', true)
            ->orderBy('redeemed_at', 'desc')
            ->paginate(10, ['*'], 'redeemed_page');

        // --- Short-code capacity & collision stats ---
        $shortCapacity = 26 * 26 * 1000; // ABnnn

        // Count how many prize_codes in DB match the short-format (A-Z A-Z 0-9 x3)
        $allCodes = PrizeExchange::pluck('prize_code');
        $usedShortCount = collect($allCodes)->filter(function ($c) {
            return is_string($c) && preg_match('/^[A-Z]{2}[0-9]{3}$/', $c);
        })->count();

        $remainingShort = max(0, $shortCapacity - $usedShortCount);

        // collisions: generation_attempts > 1 means it had to retry due to collisions
        $collisionCount = PrizeExchange::where('generation_attempts', '>', 1)->count();
        $totalExchanges = PrizeExchange::count() ?: 1; // avoid div0
        $collisionRate = ($collisionCount / $totalExchanges) * 100;
        $averageAttempts = PrizeExchange::avg('generation_attempts') ?: 0;

        // マーカー別スキャン統計 - ページネーション不要
        $markerStats = MarkerScan::select('marker_id', 'marker_name')
            ->selectRaw('COUNT(*) as total_scans')
            ->selectRaw('COUNT(DISTINCT fingerprint) as unique_users')
            ->selectRaw('MAX(scanned_at) as last_scan')
            ->groupBy('marker_id', 'marker_name')
            ->orderBy('total_scans', 'desc')
            ->get();

        // 日別スキャン数（直近30日間）- ページネーション
        $dailyScans = MarkerScan::select(DB::raw('DATE(scanned_at) as date'))
            ->selectRaw('COUNT(*) as count')
            ->where('scanned_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->paginate(15, ['*'], 'daily_page');

        // --- 追加: 日別ユニークユーザ数（fingerprint 単位）の集計（デフォルト直近30日） ---
        $uniqueDays = intval($request->query('unique_days', 30));
        $uniqueStart = Carbon::today()->subDays($uniqueDays - 1)->startOfDay();

        $rawUnique = DB::table('marker_scans')
            ->select(DB::raw("DATE(scanned_at) AS day"))
            ->selectRaw('COUNT(DISTINCT fingerprint) AS unique_count')
            ->where('scanned_at', '>=', $uniqueStart)
            ->groupBy('day')
            ->orderBy('day', 'asc')
            ->get()
            ->keyBy('day');

        // 穴埋め: 日付が連続するようにラベルと値の配列を作る
        $uniqueLabels = [];
        $uniqueCounts = [];
        for ($i = 0; $i < $uniqueDays; $i++) {
            $d = $uniqueStart->copy()->addDays($i);
            $label = $d->format('Y-m-d');
            $uniqueLabels[] = $label;
            $uniqueCounts[] = isset($rawUnique[$label]) ? (int)$rawUnique[$label]->unique_count : 0;
        }

        // 最近のスキャン履歴 - ページネーション
        $recentScans = MarkerScan::orderBy('scanned_at', 'desc')
            ->paginate(30, ['*'], 'scans_page');

        return view('admin.dashboard', compact(
            'totalExchanges',
            'redeemedExchanges',
            'pendingExchanges',
            'recentExchanges',
            'redeemedPrizes',
            'markerStats',
            'dailyScans',
            'recentScans',
            // added stats
            'shortCapacity',
            'usedShortCount',
            'remainingShort',
            'collisionCount',
            'collisionRate',
            'averageAttempts',
            // daily unique users
            'uniqueLabels',
            'uniqueCounts',
            'uniqueDays'
        ));
    }

    // 景品コードを使用済みにする
    public function redeemPrize(Request $request, $id)
    {
        $exchange = PrizeExchange::findOrFail($id);
        $exchange->is_redeemed = true;
        $exchange->redeemed_at = now();
        $exchange->save();

        return response()->json(['success' => true]);
    }

    // 全景品交換一覧
    public function allExchanges()
    {
        $exchanges = PrizeExchange::orderBy('exchanged_at', 'desc')->paginate(50);
        return view('admin.exchanges', compact('exchanges'));
    }

    // 全スキャン履歴
    public function allScans()
    {
        $scans = MarkerScan::orderBy('scanned_at', 'desc')->paginate(100);
        return view('admin.scans', compact('scans'));
    }

    // CSV エクスポート
    public function exportCsv(Request $request)
    {
        $dataset = $request->query('dataset', 'recentExchanges');
        $start = $request->query('start');
        $end = $request->query('end');

        $allowed = ['recentExchanges', 'redeemedPrizes', 'allExchanges', 'recentScans', 'dailyScans', 'markerStats'];
        if (!in_array($dataset, $allowed)) {
            return abort(400, 'Invalid dataset');
        }

        // パース：start/end があれば Carbon に変換
        $startDt = $start ? Carbon::parse($start) : null;
        $endDt = $end ? Carbon::parse($end) : null;

        $filename = sprintf('dashboard_%s_%s.csv', $dataset, now()->format('Ymd_His'));

        $callback = function () use ($dataset, $startDt, $endDt) {
            $handle = fopen('php://output', 'w');

            // ヘッダとストリーム内容
            if ($dataset === 'recentExchanges' || $dataset === 'allExchanges' || $dataset === 'redeemedPrizes') {
                // ヘッダ
                fputcsv($handle, ['id', 'prize_code', 'fingerprint', 'is_redeemed', 'exchanged_at', 'redeemed_at']);

                $query = PrizeExchange::query();
                if ($dataset === 'recentExchanges') {
                    $query->where('is_redeemed', false);
                }
                if ($dataset === 'redeemedPrizes') {
                    $query->where('is_redeemed', true);
                }
                // redeemedPrizes の場合は redeemed_at を使い、その他は exchanged_at でフィルタ
                if ($dataset === 'redeemedPrizes') {
                    if ($startDt) $query->where('redeemed_at', '>=', $startDt);
                    if ($endDt) $query->where('redeemed_at', '<=', $endDt);
                } else {
                    if ($startDt) $query->where('exchanged_at', '>=', $startDt);
                    if ($endDt) $query->where('exchanged_at', '<=', $endDt);
                }

                $query->orderBy('exchanged_at', 'desc')->chunk(200, function ($rows) use ($handle) {
                    foreach ($rows as $row) {
                        fputcsv($handle, [
                            $row->id,
                            $row->prize_code,
                            $row->fingerprint,
                            $row->is_redeemed ? '1' : '0',
                            $row->exchanged_at ? $row->exchanged_at->toDateTimeString() : '',
                            $row->redeemed_at ? $row->redeemed_at->toDateTimeString() : '',
                        ]);
                    }
                });

            } elseif ($dataset === 'recentScans') {
                fputcsv($handle, ['id', 'marker_id', 'marker_name', 'fingerprint', 'scan_count', 'scanned_at', 'device_info']);

                $query = MarkerScan::orderBy('scanned_at', 'desc');
                if ($startDt) $query->where('scanned_at', '>=', $startDt);
                if ($endDt) $query->where('scanned_at', '<=', $endDt);

                $query->chunk(500, function ($rows) use ($handle) {
                    foreach ($rows as $row) {
                        fputcsv($handle, [
                            $row->id,
                            $row->marker_id,
                            $row->marker_name,
                            $row->fingerprint,
                            $row->scan_count,
                            $row->scanned_at ? $row->scanned_at->toDateTimeString() : '',
                            is_array($row->device_info) ? json_encode($row->device_info, JSON_UNESCAPED_UNICODE) : $row->device_info,
                        ]);
                    }
                });

            } elseif ($dataset === 'dailyScans') {
                // 日次集計を出力
                fputcsv($handle, ['date', 'count']);
                $query = MarkerScan::select(DB::raw('DATE(scanned_at) as date'))
                    ->selectRaw('COUNT(*) as count')
                    ->groupBy('date')
                    ->orderBy('date', 'desc');

                if ($startDt) $query->having('date', '>=', $startDt->toDateString());
                if ($endDt) $query->having('date', '<=', $endDt->toDateString());

                $rows = $query->get();
                foreach ($rows as $r) {
                    fputcsv($handle, [$r->date, $r->count]);
                }

            } elseif ($dataset === 'markerStats') {
                // マーカー集計（期間指定があればその期間のスキャンで再集計）
                fputcsv($handle, ['marker_id', 'marker_name', 'total_scans', 'unique_users', 'last_scan']);

                $query = MarkerScan::query();
                if ($startDt) $query->where('scanned_at', '>=', $startDt);
                if ($endDt) $query->where('scanned_at', '<=', $endDt);

                $rows = $query->select('marker_id', 'marker_name')
                    ->selectRaw('COUNT(*) as total_scans')
                    ->selectRaw('COUNT(DISTINCT fingerprint) as unique_users')
                    ->selectRaw('MAX(scanned_at) as last_scan')
                    ->groupBy('marker_id', 'marker_name')
                    ->orderBy('total_scans', 'desc')
                    ->get();

                foreach ($rows as $r) {
                    fputcsv($handle, [$r->marker_id, $r->marker_name, $r->total_scans, $r->unique_users, $r->last_scan]);
                }
            }

            fclose($handle);
        };

        // ストリーミングダウンロード
        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"'
        ]);
    }

    // ARstampRally202603用のダッシュボード（パンダマーカーの統計）
    public function dashboard202603(Request $request)
    {
        // 2026年1月〜3月の日付範囲（JST→UTC変換）
        $startDate = Carbon::createFromFormat('Y-m-d H:i:s', '2026-01-01 00:00:00', 'Asia/Tokyo')
                           ->setTimezone('UTC');
        $endDate   = Carbon::createFromFormat('Y-m-d H:i:s', '2026-03-31 23:59:59', 'Asia/Tokyo')
                           ->setTimezone('UTC');

        // 【新規追加】景品交換の統計
        $totalExchanges = PrizeExchange::whereBetween('exchanged_at', [$startDate, $endDate])
            ->count();
        $redeemedExchanges = PrizeExchange::where('is_redeemed', true)
            ->whereBetween('exchanged_at', [$startDate, $endDate])
            ->count();
        $pendingExchanges = $totalExchanges - $redeemedExchanges;

        // 【新規追加】最近の景品交換（未使用のみ）- ページネーション
        // optional search by prize code (query param: q)
        $q = $request->query('q');
        $recentExchangesQuery = PrizeExchange::where('is_redeemed', false)
            ->whereBetween('exchanged_at', [$startDate, $endDate]);
        if ($q) {
            // allow partial matches (case-insensitive)
            $recentExchangesQuery->where('prize_code', 'like', '%' . strtoupper($q) . '%');
        }
        $recentExchanges = $recentExchangesQuery->orderBy('exchanged_at', 'desc')
            ->paginate(20, ['*'], 'exchanges_page')->appends(['q' => $q]);

        // 【新規追加】使用済み景品交換 - ページネーション（10件ごと）
        $redeemedPrizes = PrizeExchange::where('is_redeemed', true)
            ->whereBetween('exchanged_at', [$startDate, $endDate])
            ->orderBy('redeemed_at', 'desc')
            ->paginate(10, ['*'], 'redeemed_page');

        // 全動物のリスト（ARstampRally202603.blade.phpのSTAMPSと同じ順序）
        $animals = [
            'tomato' => 'トマト',
            'fox' => 'きつね',
            'pengin' => 'ペンギン',
            'red_panda' => 'レッサーパンダ',
            'koara' => 'コアラ',
            'tora' => 'とら',
            'kame' => 'カメ',
            'cheetah' => 'チーター',
            'blockoly' => 'ブロッコリー',
            'araiguma' => 'アライグマ',
            'potato' => 'ポテト',
            'miacat' => 'ミーアキャット',
            'kapibara' => 'カピバラ',
            'lion' => 'ライオン',
            'hamstar' => 'ハムスター',
            // シークレット動物
            'barger' => 'バーガー',
            'kirin' => 'きりん',
            'aeon' => 'イオちゃん',
            'pet' => 'ペットボトル',
            'panda' => 'パンダ'
        ];
        
        // 各動物の統計を収集
        $animalStats = [];
        foreach ($animals as $markerId => $markerName) {
            // マーカー検出回数（capture_type: 'marker_scan'）
            $markerScanCount = MarkerScan::where('marker_id', $markerId)
                ->where('capture_type', 'marker_scan')
                ->whereBetween('scanned_at', [$startDate, $endDate])
                ->count();
            
            // ボールヒット回数（capture_type: 'ball_hit'）
            $ballHitCount = MarkerScan::where('marker_id', $markerId)
                ->where('capture_type', 'ball_hit')
                ->whereBetween('scanned_at', [$startDate, $endDate])
                ->count();
            
            // 合計
            $totalCount = $markerScanCount + $ballHitCount;
            
            // ユニークユーザー数（fingerprint別）
            $uniqueUsers = MarkerScan::where('marker_id', $markerId)
                ->whereBetween('scanned_at', [$startDate, $endDate])
                ->distinct('fingerprint')
                ->count();
            
            // 最終スキャン日時
            $lastScan = MarkerScan::where('marker_id', $markerId)
                ->whereBetween('scanned_at', [$startDate, $endDate])
                ->orderBy('scanned_at', 'desc')
                ->first();
            
            $animalStats[] = [
                'marker_id' => $markerId,
                'marker_name' => $markerName,
                'marker_scan_count' => $markerScanCount,
                'ball_hit_count' => $ballHitCount,
                'total_count' => $totalCount,
                'unique_users' => $uniqueUsers,
                'last_scan' => $lastScan ? $lastScan->scanned_at : null
            ];
        }
        
        // 最近のスキャン履歴（全動物、タイプ別）
        $recentScans = MarkerScan::whereBetween('scanned_at', [$startDate, $endDate])
            ->orderBy('scanned_at', 'desc')
            ->paginate(30, ['*'], 'recent_scans_page');
        
        // 日別統計（タイプ別、2026年1月〜3月）
        $dailyStatsRaw = MarkerScan::select(DB::raw('DATE(scanned_at) as date'))
            ->selectRaw('capture_type')
            ->selectRaw('COUNT(*) as count')
            ->whereBetween('scanned_at', [$startDate, $endDate])
            ->groupBy('date', 'capture_type')
            ->orderBy('date', 'desc')
            ->get();
        
        // 日付でグループ化
        $dailyStats = $dailyStatsRaw->groupBy('date');
        
        // 【新規】日別個別ユーザー数統計（タイプ別、2026年1月〜3月）
        $dailyUniqueUsersRaw = MarkerScan::select(DB::raw('DATE(scanned_at) as date'))
            ->selectRaw('capture_type')
            ->selectRaw('COUNT(DISTINCT fingerprint) as unique_users')
            ->whereBetween('scanned_at', [$startDate, $endDate])
            ->groupBy('date', 'capture_type')
            ->orderBy('date', 'desc')
            ->get();
        
        // 日付でグループ化
        $dailyUniqueUsers = $dailyUniqueUsersRaw->groupBy('date');
        
        return view('admin.dashboard202603', compact(
            'animalStats',
            'recentScans',
            'dailyStats',
            'dailyUniqueUsers',
            'totalExchanges',
            'redeemedExchanges',
            'pendingExchanges',
            'recentExchanges',
            'redeemedPrizes'
        ));
    }
}
