<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>管理ダッシュボード - ARスタンプラリー</title>
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
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        .redeem-btn {
            padding: 6px 12px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        .redeem-btn:hover {
            background: #218838;
        }
        .redeem-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .prize-code {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            font-size: 16px;
            color: #667eea;
        }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #667eea;
            transition: all 0.3s;
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
        .prizes-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 1200px) {
            .prizes-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 ARスタンプラリー 管理ダッシュボード</h1>
        <a href="{{ route('admin.logout') }}" class="logout-btn">ログアウト</a>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>総景品交換数</h3>
            <div class="number">{{ $totalExchanges }}</div>
        </div>
        <div class="stat-card">
            <h3>使用済み</h3>
            <div class="number" style="color: #28a745;">{{ $redeemedExchanges }}</div>
        </div>
        <div class="stat-card">
            <h3>未使用</h3>
            <div class="number" style="color: #ffc107;">{{ $pendingExchanges }}</div>
        </div>
    </div>

    <div class="prizes-grid">
        <div class="card">
            <h2>🎁 未使用の景品交換</h2>
            <table>
                <thead>
                    <tr>
                        <th>景品コード</th>
                        <th>交換日時</th>
                        <th>フィンガープリント</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentExchanges as $exchange)
                    <tr>
                        <td class="prize-code">{{ $exchange->prize_code }}</td>
                        <td>{{ $exchange->exchanged_at->format('Y/m/d H:i:s') }}</td>
                        <td style="font-size: 12px; color: #666;">{{ Str::limit($exchange->fingerprint, 20) }}</td>
                        <td>
                            <button class="redeem-btn" onclick="redeemPrize({{ $exchange->id }})">
                                使用済みにする
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" style="text-align: center; color: #999;">データがありません</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            {{ $recentExchanges->links() }}
        </div>

        <div class="card">
            <h2>✅ 使用済み景品交換</h2>
            <table>
                <thead>
                    <tr>
                        <th>景品コード</th>
                        <th>交換日時</th>
                        <th>使用日時</th>
                        <th>フィンガープリント</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($redeemedPrizes as $prize)
                    <tr>
                        <td class="prize-code" style="color: #999;">{{ $prize->prize_code }}</td>
                        <td style="font-size: 13px;">{{ $prize->exchanged_at->format('Y/m/d H:i') }}</td>
                        <td style="font-size: 13px; color: #28a745;">
                            {{ $prize->redeemed_at ? $prize->redeemed_at->format('Y/m/d H:i') : '-' }}
                        </td>
                        <td style="font-size: 12px; color: #666;">{{ Str::limit($prize->fingerprint, 20) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" style="text-align: center; color: #999;">データがありません</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            {{ $redeemedPrizes->links() }}
        </div>
    </div>

    <div class="card">
        <h2>🏷️ マーカー別スキャン統計</h2>
        <div class="chart-section">
            <div>
                <table>
                    <thead>
                        <tr>
                            <th>マーカーID</th>
                            <th>マーカー名</th>
                            <th>総スキャン数</th>
                            <th>ユニークユーザー数</th>
                            <th>最終スキャン日時</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($markerStats as $stat)
                        <tr>
                            <td>{{ $stat->marker_id }}</td>
                            <td>{{ $stat->marker_name }}</td>
                            <td><strong>{{ $stat->total_scans }}</strong></td>
                            <td>{{ $stat->unique_users }}</td>
                            <td>{{ \Carbon\Carbon::parse($stat->last_scan)->format('Y/m/d H:i:s') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" style="text-align: center; color: #999;">データがありません</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="chart-container">
                <h3>📊 スキャン数グラフ</h3>
                <div class="bar-chart">
                    @php
                        $maxScans = $markerStats->max('total_scans') ?: 1;
                    @endphp
                    @foreach($markerStats as $stat)
                        <div class="bar-item">
                            <div class="bar-label">{{ $stat->marker_name }}</div>
                            <div class="bar-wrapper">
                                <div class="bar-fill" style="width: {{ ($stat->total_scans / $maxScans) * 100 }}%">
                                    {{ $stat->total_scans }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>📅 日別スキャン数（直近30日間）</h2>
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
                        @forelse($dailyScans as $daily)
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
                {{ $dailyScans->links() }}
            </div>
            <div class="chart-container">
                <h3>📊 日別スキャン推移</h3>
                <div class="bar-chart">
                    @php
                        $maxDaily = $dailyScans->max('count') ?: 1;
                        $currentPageData = $dailyScans->reverse();
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

    <div class="card">
        <h2>📝 最近のスキャン履歴</h2>
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
                @forelse($recentScans as $scan)
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
        {{ $recentScans->links() }}
    </div>

    <script>
        function redeemPrize(id) {
            if (!confirm('この景品コードを使用済みにしますか？')) {
                return;
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

            fetch(`{{ url('/admin/prizes') }}/${id}/redeem`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('使用済みにしました');
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('エラーが発生しました');
            });
        }

        // 30秒ごとに自動更新（ページ番号を保持）
        setTimeout(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
