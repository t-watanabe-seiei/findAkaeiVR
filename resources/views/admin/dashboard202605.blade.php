<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>管理ダッシュボード202605 - ARスタンプラリー</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .marker-scan {
            color: #3498db;
            font-weight: bold;
        }
        .ball-hit {
            color: #e74c3c;
            font-weight: bold;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            color: white;
        }
        .badge.marker {
            background-color: #3498db;
        }
        .badge.ball {
            background-color: #e74c3c;
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
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            min-height: 36px;
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
        /* ページネーションのSVGアイコンサイズ制御 */
        .pagination svg {
            width: 18px !important;
            height: 18px !important;
            max-width: 18px !important;
            max-height: 18px !important;
        }
        /* Laravelページネーションの構造に対応 */
        .pagination nav {
            display: flex;
            justify-content: center;
        }
        .pagination nav svg {
            width: 18px !important;
            height: 18px !important;
        }
        /* 【新規追加】.pagination-wrapper内のページネーションのSVGアイコンサイズ制御 */
        .pagination-wrapper svg {
            width: 18px !important;
            height: 18px !important;
            max-width: 18px !important;
            max-height: 18px !important;
        }
        .pagination-wrapper nav svg {
            width: 18px !important;
            height: 18px !important;
        }
        .chart-container {
            margin-top: 30px;
            max-width: 1200px;
        }
        canvas {
            max-height: 400px;
        }
        
        /* 【新規】景品交換統計カード用のグリッドレイアウト */
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
        
        /* 【新規】景品交換セクション用のグリッドレイアウト */
        .prizes-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 1200px) {
            .prizes-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* 【新規】景品コード表示用のスタイル */
        .prize-code {
            font-family: 'Courier New', monospace;
            font-weight: bold;
            font-size: 16px;
            color: #667eea;
        }
        
        /* 【新規】使用済みボタンのスタイル */
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
        
        /* 【新規】ページネーションラッパー */
        .pagination-wrapper {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 ARスタンプラリー202605 管理ダッシュボード（全モデル統計）</h1>
        <div class="nav-links">
            <a href="{{ route('admin.dashboard') }}" class="nav-link">通常ダッシュボード</a>
            <a href="{{ route('admin.logout') }}" class="logout-btn">ログアウト</a>
        </div>
    </div>

    <!-- 【新規追加】景品交換統計カード -->
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

    <div class="card">
        <h2>🐾 動物別統計</h2>
        <table>
            <thead>
                <tr>
                    <th>動物名</th>
                    <th class="marker-scan">マーカー検出回数</th>
                    <th class="ball-hit">ボールヒット回数</th>
                    <th>合計</th>
                    <th>ユニークユーザー数</th>
                    <th>最終スキャン</th>
                </tr>
            </thead>
            <tbody>
                @foreach($animalStats as $stat)
                <tr>
                    <td>{{ $stat['marker_name'] }} ({{ $stat['marker_id'] }})</td>
                    <td class="marker-scan">{{ $stat['marker_scan_count'] }}</td>
                    <td class="ball-hit">{{ $stat['ball_hit_count'] }}</td>
                    <td><strong>{{ $stat['total_count'] }}</strong></td>
                    <td>{{ $stat['unique_users'] }}</td>
                    <td>{{ $stat['last_scan'] ? $stat['last_scan']->tz('Asia/Tokyo')->format('Y/m/d H:i') : '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>📝 最近のスキャン履歴</h2>
        <table>
            <thead>
                <tr>
                    <th>日時</th>
                    <th>マーカー</th>
                    <th>タイプ</th>
                    <th>スキャン回数</th>
                    <th>フィンガープリント</th>
                    <th>デバイス</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentScans as $scan)
                <tr>
                    <td>{{ $scan->scanned_at->tz('Asia/Tokyo')->format('Y/m/d H:i:s') }}</td>
                    <td>{{ $scan->marker_name }}</td>
                    <td>
                        <span class="badge {{ $scan->capture_type === 'marker_scan' ? 'marker' : 'ball' }}">
                            {{ $scan->capture_type === 'marker_scan' ? 'マーカー検出' : 'ボールヒット' }}
                        </span>
                    </td>
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
                    <td colspan="6" style="text-align: center; color: #999;">データがありません</td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="pagination">
            {{ $recentScans->links() }}
        </div>
    </div>

    <div class="card">
        <h2>📅 日別スキャン統計（2026年5月）</h2>
        <div class="chart-container">
            <canvas id="dailyChart"></canvas>
        </div>
    </div>

    <div class="card">
        <h2>👥 日別個別ユーザー数（2026年5月）</h2>
        <div class="chart-container">
            <canvas id="uniqueUsersChart"></canvas>
        </div>
    </div>

    <!-- 【新規追加】景品交換セクション -->
    <div class="prizes-grid">
        <!-- 未使用の景品交換 -->
        <div class="card">
            <h2>🎁 未使用の景品交換</h2>
            <div style="margin:8px 0 16px; display:flex; gap:8px; align-items:center;">
                <form method="GET" action="{{ route('admin.dashboard202605') }}" style="display:flex; gap:8px; align-items:center;">
                    <input type="search" name="q" placeholder="景品コードで検索 (例: AB123)" value="{{ request('q') }}" style="padding:6px 8px; border:1px solid #ddd; border-radius:6px;" />
                    <button type="submit" style="padding:6px 10px; background:#667eea; color:white; border:none; border-radius:6px; cursor:pointer;">検索</button>
                    @if(request('q'))
                        <a href="{{ route('admin.dashboard202605') }}" style="padding:6px 10px; background:#e0e0e0; color:#333; border-radius:6px; text-decoration:none;">クリア</a>
                    @endif
                </form>
            </div>
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
                        <td>{{ $exchange->exchanged_at->tz('Asia/Tokyo')->format('Y/m/d H:i:s') }}</td>
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
            <div class="pagination-wrapper">
                {{ $recentExchanges->links() }}
            </div>
        </div>

        <!-- 使用済み景品交換 -->
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
                        <td style="font-size: 13px;">{{ $prize->exchanged_at->tz('Asia/Tokyo')->format('Y/m/d H:i') }}</td>
                        <td style="font-size: 13px; color: #28a745;">
                            {{ $prize->redeemed_at ? $prize->redeemed_at->tz('Asia/Tokyo')->format('Y/m/d H:i') : '-' }}
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
            <div class="pagination-wrapper">
                {{ $redeemedPrizes->links() }}
            </div>
        </div>
    </div>

    <script>
        // 【新規追加】景品コードを使用済みにする関数
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

        // 日別統計グラフ（積み上げ棒グラフ）
        const dailyData = @json($dailyStats);
        const dates = Object.keys(dailyData).reverse();
        
        const markerScanData = dates.map(date => {
            const dayData = dailyData[date].find(d => d.capture_type === 'marker_scan');
            return dayData ? dayData.count : 0;
        });
        
        const ballHitData = dates.map(date => {
            const dayData = dailyData[date].find(d => d.capture_type === 'ball_hit');
            return dayData ? dayData.count : 0;
        });
        
        const ctx = document.getElementById('dailyChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: dates.map(date => {
                    const d = new Date(date);
                    return (d.getMonth() + 1) + '/' + d.getDate();
                }),
                datasets: [
                    {
                        label: 'マーカー検出',
                        data: markerScanData,
                        backgroundColor: 'rgba(52, 152, 219, 0.6)',
                        borderColor: 'rgba(52, 152, 219, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'ボールヒット',
                        data: ballHitData,
                        backgroundColor: 'rgba(231, 76, 60, 0.6)',
                        borderColor: 'rgba(231, 76, 60, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    x: {
                        stacked: true,
                        title: {
                            display: true,
                            text: '日付'
                        }
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'スキャン数'
                        },
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: 'タイプ別日別スキャン数（積み上げ）'
                    }
                }
            }
        });

        // 【新規】日別個別ユーザー数グラフ（折れ線グラフ）
        const uniqueUsersData = @json($dailyUniqueUsers);
        const uniqueDates = Object.keys(uniqueUsersData).reverse();
        
        const markerScanUniqueData = uniqueDates.map(date => {
            const dayData = uniqueUsersData[date].find(d => d.capture_type === 'marker_scan');
            return dayData ? dayData.unique_users : 0;
        });
        
        const ballHitUniqueData = uniqueDates.map(date => {
            const dayData = uniqueUsersData[date].find(d => d.capture_type === 'ball_hit');
            return dayData ? dayData.unique_users : 0;
        });
        
        const ctxUnique = document.getElementById('uniqueUsersChart').getContext('2d');
        new Chart(ctxUnique, {
            type: 'line',
            data: {
                labels: uniqueDates.map(date => {
                    const d = new Date(date);
                    return (d.getMonth() + 1) + '/' + d.getDate();
                }),
                datasets: [
                    {
                        label: 'マーカー検出（個別ユーザー）',
                        data: markerScanUniqueData,
                        borderColor: 'rgba(52, 152, 219, 1)',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    },
                    {
                        label: 'ボールヒット（個別ユーザー）',
                        data: ballHitUniqueData,
                        borderColor: 'rgba(231, 76, 60, 1)',
                        backgroundColor: 'rgba(231, 76, 60, 0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: '日付'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: '個別ユーザー数'
                        },
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: '日別個別ユーザー数推移'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y + '人';
                            }
                        }
                    }
                }
            }
        });

        // 30秒ごとに自動更新
        setTimeout(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
