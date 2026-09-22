# ARstampRally202609 要件定義（iOS カメラ起動 表示遅延の改修）

> 作成日: 2026-09-23
> 本ファイルは 202609 側の「iPhone SE3 等での表示が遅い（ローダー長時間表示）」改修の要件定義である。
> 過去の経緯は `analysis20260911.md`（video セレクタ修正・2026-09-11 適用済み）を参照。

## 1. 目的
iPhone SE 3rd（iOS 17.2）で、`ARstampRally202610` は直ちに起動するのに対し、
`ARstampRally202609` は**「AR を起動中...」（ローダー）画面が長時間残り、表示が遅い**状態となる。
202610 と 202609 のコード差分を調査した結果、起動まわりの機能差は**202610 にのみ存在する
カメラ監視・再試行ロジック（FR-9 / FR-10 / FR-11 相当）**であることが特定された。
本改修は、そのロジックを **202609 へバックポート**して 202610 と同一の挙動を得ることを目的とする。

## 2. 症状と原因（調査結論・2026-09-23）
| # | 症状 / 原因 | 202610 | 202609（改修前） |
|---|---|---|---|
| C1 | video が ready になった時点でローダー `.arjs-loader` を即非表示にする `hideCameraErrorUI()` | あり | **なし**（`arjs-video-loaded` イベントまたは iOS 15 秒フォールバックまでローダーが残る） |
| C2 | タイムアウト後の重複表示を `timedOut` フラグで抑制し、ready 時 `_pendingCameraError=false` に復位 | あり | なし |
| C3 | 再試行タップ時、`video.srcObject` が既にあれば 2度目 `getUserMedia` を行わず `play()` のみ（FR-10） | あり | **なし**（iOS でストリーム競合 → 全制約失敗 → 2回目でページ再起動ループ） |
| C4 | `#camera-error` 文言の導線（「起動を確認しています」/ FR-11） | あり | 旧文言（「カメラを起動できません」） |

> 両者とも `cameraStartupTimeout()`（iOS=15000ms / 他=7000ms）・`arjs-video-loaded` ハンドラ・
> フォールバック `hideArjsLoader`・video セレクタ（`querySelector('video')`）は**同一**。
> 上記 C1〜C4 のみ実質差である。

## 3. 機能要件
| # | 要件 |
|---|---|
| FR-1 | `monitorCameraStartup` はタイムアウト後も監視を継続し、video が ready になった時点で `#camera-error` / `#camera-help-modal` / **`.arjs-loader`** を自動非表示にする（202610 と同一ロジック） |
| FR-2 | `monitorCameraStartup` は `timedOut` フラグでタイムアウト時分岐を1回に抑制し、ready 時点で `window._pendingCameraError=false` に復位する（202610 と同一ロジック） |
| FR-3 | `ensureCameraAccess`（再試行タップ時）は `video.srcObject` が既に存在する場合、新たな `getUserMedia` を発行せず `video.play()` のみ試行して即復帰を試みる（失敗しても監視継続で自動復帰） |
| FR-4 | `ui.blade.php` の `#camera-error` 文言を 202610（FR-11）の導線に準拠させる（**ボタン構造 `再試行` / `低解像度で再試行` は現状維持**） |
| FR-5 | 修正対象は `ARstampRally202609` のパーシャル（head / ui）のみ。202610・他キャンペーン・共有JS（`public/js/`）・`public/cg/` への変更は行わない（分離原則） |
| FR-6 | 202609 固有識別子（LocalStorage / Cookie / `[AR202609]` ログプレフィックス / アセットパス `cg/202609/` / アニメクリップ `anime01` 等）を変更しない |
| FR-7 | Android（Chrome・WebXR）経路の起動・操作挙動を変えない（監視継続・ローダー即非表示は Android でも「起動遅延の自動復帰」として有効） |

## 4. 非機能要件・制約
- 現在正常に動作している箇所（スタンプ集計 / 景品交換 / ギャラリー / ボール投擲 / マーカー認識）への影響をゼロにする。
- 変更範囲は最小限（`head.blade.php` の2関数・`ui.blade.php` の文言2行のみ）。
- 変更後は `php -l` / ビューレンダリング / トークン検証を実機検証に代わる確認として実施する。
