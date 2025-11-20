<?php

namespace App\Http\Controllers;

use App\Models\MarkerScan;
use App\Models\PrizeExchange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
}
