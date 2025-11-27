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
        $recentExchanges = PrizeExchange::where('is_redeemed', false)
            ->orderBy('exchanged_at', 'desc')
            ->paginate(20, ['*'], 'exchanges_page');

        // 使用済み景品交換 - ページネーション（10件ごと）
        $redeemedPrizes = PrizeExchange::where('is_redeemed', true)
            ->orderBy('redeemed_at', 'desc')
            ->paginate(10, ['*'], 'redeemed_page');

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
            'recentScans'
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
}
