# ARstampRally202609 設計書（iOS カメラ起動 表示遅延の改修）

> 作成日: 2026-09-23 / 要件: `requirements.md`（FR-1〜FR-7）

## 1. 現状フロー（改修前の 202609）
1. `js-init.blade.php` / `window.load`:
   - `window.cameraStartupTimeout()` = iOS`15000`ms / 他`7000`ms（202610 と同一）。
   - `setTimeout(hideArjsLoader, cameraStartupTimeout())` でローダー強制非表示（フォールバック）。
   - 既存 `video` の `play()` 試行（iOS autoplay ブロック対策）。
   - `monitorCameraStartup(cameraStartupTimeout())` 開始。
2. `head.blade.php` / `monitorCameraStartup`（500ms 間隔ポーリング）:
   - `querySelector('video')` が `readyState >= 2 || currentTime > 0 || !paused` なら
     `#camera-error` / `#camera-help-modal` を非表示して監視停止（**`.arjs-loader` は対象外**）。
   - タイムアウト到達で `#camera-error`（+低解像度ボタン）を表示し監視は継続するが、
     **`timedOut` フラグが無く 500ms ごとに重複表示分岐が実行される**。
3. `head.blade.php` / `ensureCameraAccess`（再試行タップ時）:
   - `srcObject` 有 + ready なら即戻る。
   - **`srcObject` 有 + 未 ready でも 4 制約セットの 2度目 `getUserMedia` を発行**
     → iOS でストリーム競合 → 全失敗 → 2回目以降で `location.reload()`（起動ループ）。
4. `ui.blade.php` / `#camera-error`: 旧文言（「カメラを起動できません」）。

## 2. 202610 との機能差分（バックポート対象）
| 差分 | 202610 実装 | 202609 への反映 |
|---|---|---|
| `timedOut` フラグ | `let timedOut=false;`、`if (!timedOut && elapsed>ms) { timedOut=true; ... }` | 同一コードを移植 |
| ready 時 UI 抑制 | `hideCameraErrorUI()` で `#camera-error` / `#camera-help-modal` / **`.arjs-loader`** を非表示 | 同一コードを移植（**ローダー即時消えが SE3 症状の本命修正**） |
| ready 時フラグ復位 | `window._pendingCameraError=false;` | 同一コードを移植 |
| FR-10（二重 getUserMedia 防止） | `srcObject` 有なら `playsinline` 設定 + `muted` + `play().catch(...)` して即 return | 同一コードを移植（ログは `[AR202609]` プレフィックス） |
| FR-11（文言） | 「カメラの起動を確認しています」＋「タップで再開されることがあります」導線 | `ui.blade.php` の h2/p を置換（ボタン構造は変更なし） |

## 3. 改修設計
### 3.1 `head.blade.php` / `monitorCameraStartup`（FR-1 / FR-2）
- 202610 の実装と**ロジック同一**に置換（コメント日付のみ 202609 向けに書き換え）。
- `document.querySelector('video')`（2026-09-11 に適用済みのセレクタ）は維持。
- `window.guideModalOpen` 中の `_pendingCameraError` 保持挙動は維持。
- タイムアウト値は呼び出し側（`js-init`）のままで変更しない。

### 3.2 `head.blade.php` / `ensureCameraAccess`（FR-3）
- 冒頭の「`srcObject` 有 + ready なら return」判定の**直後に**、
  「`srcObject` 有 + 未 ready」向けブロックを挿入:
  ```
  if (v && v.srcObject) {
      playsinline 確保 → muted=true → play().catch(warn) → return;
  }
  ```
- その後の 4 制約セット再要求ロジック（`srcObject` が無い場合）は**変更しない**。
- `getUserMedia` 成功ハンドラ（`.arjs-loader` 非表示・`camera-error` 非表示）は変更しない。

### 3.3 `ui.blade.php` / `#camera-error`（FR-4）
- `h2` = 「カメラの起動を確認しています」、`p` = 202610（FR-11）の文言に置換。
- 要素 ID・構造・インラインスタイル・ボタン（`#retry-camera` / `#retry-camera-lowres`）は**現状維持**。

### 3.4 変更しないもの（影響ゼロ保証・FR-5 / FR-6 / FR-7）
- `js-init / js-stamps / js-prize / js-throw / js-gallery / js-camera / aframe-components / scene`（202609 内）
- `ARstampRally202610/` 全ファイル・他キャンペーン・`public/js/`・`public/cg/`
- 202609 固有のストレージキー・ルート・API・レートリミッター・アニメクリップ名

## 4. 影響範囲とリスク
| 項目 | 影響 |
|---|---|
| iPhone SE3（iOS Safari） | video ready 時点でローダー即非表示（従来は最大15秒残存）／再試行タップの競合失敗・再起動ループの解消 |
| Android（Chrome・WebXR） | 監視継続・ローダー即非表示は「起動遅延の自動復帰」として有効化。それ以外不変 |
| 正常系（起動即 ready） | `hideCameraErrorUI()` は既に非表示の要素への操作のみで無害。`play()` 試行は gesture 内のみ |
| スタンプ集計・景品交換・ギャラリー・投擲 | 対象コードに依存しない（`#camera-error` / ローダー / video 監視のみ）で影響なし |

## 5. 検証計画
1. `php -l`（head.blade.php / ui.blade.php）
2. トークン検証: 202609 head で `timedOut` / `hideCameraErrorUI` / `.arjs-loader` 非表示 / FR-10 ブロック（`srcObject`+`play()`）の存在、`querySelector('video')`=3件以上・`#ar-scene video` 実体0件を確認
3. レンダリング: Laravel アプリブート + `view('ARstampRally202609.head')` / `view('ARstampRally202609.ui')` の `render()` が正常な HTML を生成すること
4. 分離確認: `git status` で変更が 202609 パーシャル + 関連 md のみであることを確認（202610・`public/js/`・`public/cg/` 未変更）
