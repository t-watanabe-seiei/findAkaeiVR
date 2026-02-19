<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>管理ダッシュボード202603 - ARスタンプラリー</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
        }
        .nav-links {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .nav-link {
            padding: 10px 15px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            transition: background 0.3s;
        }
        .nav-link:hover {
            background: #45a049;
        }
        .logout-btn {
            padding: 10px 20px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
        }
        .logout-btn:hover {
            background: #c82333;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .stat-card .number {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
        }
        .card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .card h2 {
            margin-bottom: 15px;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .pagination a,
        .pagination span,
        .pagination li a,
        .pagination li span {
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #667eea;
            transition: all 0.3s;
            font-size: 14px;
            line-height: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            max-width: 56px;
            max-height: 56px;
            box-sizing: border-box;
        }
        .pagination a:hover {
            background: #667eea;
            color: white;
        }
        .pagination .active span {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        .pagination .disabled span {
            color: #ccc;
            cursor: not-allowed;
        }
        .pagination .page-link {
            font-size: 14px;
            padding: 6px 10px;
        }
        .pagination .page-item .page-link {
            font-size: 14px !important;
            padding: 6px 10px !important;
            min-width: 36px !important;
            max-width: 56px !important;
            max-height: 56px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
        .pagination .page-link svg,
        .pagination .page-link i,
        .pagination .page-link .icon,
        .pagination .page-link::before,
        .pagination .page-link::after {
            width: 18px !important;
            height: 18px !important;
            max-width: 18px !important;
            max-height: 18px !important;
            font-size: 18px !important;
            line-height: 18px !important;
            display: inline-block !important;
            vertical-align: middle !important;
        }
        .pagination li {
            display: inline-block !important;
            vertical-align: middle !important;
            margin: 0 2px !important;
        }
        .pagination {
            white-space: nowrap !important;
        }
        nav[role="navigation"] svg {
            width: 18px !important;
            height: 18px !important;
            max-width: 18px !important;
            max-height: 18px !important;
        }
        nav[role="navigation"] .w-5, nav[role="navigation"] .h-5 {
            width: 18px !important;
            height: 18px !important;
        }
        .chart-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            align-items: start;
        }
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .chart-container h3 {
            margin-bottom: 15px;
            color: #333;
            font-size: 16px;
        }
        .bar-chart {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .bar-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .bar-label {
            min-width: 100px;
            font-size: 13px;
            color: #555;
            font-weight: 500;
        }
        .bar-wrapper {
            flex: 1;
            background: #f0f0f0;
            border-radius: 4px;
            height: 24px;
            position: relative;
            overflow: hidden;
        }
        .bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            border-radius: 4px;
            transition: width 0.5s ease;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 8px;
            color: white;
            font-size: 12px;
            font-weight: bold;
            min-width: 30px;
        }
        .bar-value {
            font-size: 13px;
            font-weight: bold;
            color: #667eea;
            min-width: 50px;
            text-align: right;
        }
        @media (max-width: 1024px) {
            .chart-section {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 ARスタンプラリー202603 管理ダッシュボード（パンダ統計）</h1>
        <div class="nav-links">
            <a href="{{ route('admin.dashboard') }}" class="nav-link">通常ダッシュボード</a>
            <a href="{{ route('admin.logout') }}" class="logout-btn">ログアウト</a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>総パンダスキャン数</h3>
            <div class="number">{{ $totalPandaScans }}</div>
        </div>
        <div class="stat-card">
            <h3>ユニークユーザー数</h3>
            <div class="number" style="color: #28a745;">{{ $uniquePandaUsers }}</div>
        </div>
    </div>

    <div class="card">
        <h2>📝 最近のパンダスキャン履歴</h2>
        <table>
            <thead>
                <tr>
                    <th>日時</th>
                    <th>マーカー</th>
                    <th>スキャン回数</th>
                    <th>フィンガープリント</th>
                    <th>デバイス</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentPandaScans as $scan)
                <tr>
                    <td>{{ $scan->scanned_at->format('Y/m/d H:i:s') }}</td>
                    <td>{{ $scan->marker_name }} ({{ $scan->marker_id }})</td>
                    <td>{{ $scan->scan_count }}</td>
                    <td style="font-size: 12px; color: #666;">{{ Str::limit($scan->fingerprint, 20) }}</td>
                    <td style="font-size: 12px; color: #666;">
                        @if($scan->device_info)
                            {{ $scan->device_info['isIOS'] ?? false ? '📱 iOS' : ($scan->device_info['isAndroid'] ?? false ? '📱 Android' : '💻 PC') }}
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="text-align: center; color: #999;">データがありません</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="pagination-wrapper">
            {{ $recentPandaScans->links() }}
        </div>
    </div>

    <div class="card">
        <h2>📅 日別パンダスキャン数（直近30日間）</h2>
        <div class="chart-section">
            <div>
                <table>
                    <thead>
                        <tr>
                            <th>日付</th>
                            <th>スキャン数</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dailyPandaScans as $daily)
                        <tr>
                            <td>{{ $daily->date }}</td>
                            <td><strong>{{ $daily->count }}</strong></td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="2" style="text-align: center; color: #999;">データがありません</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="pagination-wrapper">
                    {{ $dailyPandaScans->links() }}
                </div>
            </div>
            <div class="chart-container">
                <h3>📊 日別パンダスキャン推移</h3>
                <div class="bar-chart">
                    @php
                        $maxDaily = $dailyPandaScans->max('count') ?: 1;
                        $currentPageData = $dailyPandaScans->reverse();
                    @endphp
                    @foreach($currentPageData as $daily)
                        <div class="bar-item">
                            <div class="bar-label">{{ \Carbon\Carbon::parse($daily->date)->format('m/d') }}</div>
                            <div class="bar-wrapper">
                                <div class="bar-fill" style="width: {{ ($daily->count / $maxDaily) * 100 }}%">
                                    {{ $daily->count }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <script>
        // 30秒ごとに自動更新
        setTimeout(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
