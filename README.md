## findHoufu VRゲーム（2026-07-17）

### 概要
豊後市観光地を巡る「探す＆撃つ」VRシューティングゲーム。A-Frame ベースで 7 ステージ構成。Bucchi モデルを素早く撃つことでスコアを稼ぎ、各ステージのタイムリミット内に撃破する。

### ルート
```
GET /findHoufu → view('findHoufu.index')
```

### ファイル構成
```
resources/views/findHoufu/
├── index.blade.php      # HTML シェル（CSRF meta + @include）
├── _scene.blade.php     # A-Frame シーン（アセット・コントローラー・UI・パーティクル）
└── _components.blade.php # 全 JS ロジック（5 コンポーネント + ユーティリティ）
```

### ゲームフロー
1. VR 自動入場（WebXR 検出 → 1s 後 `enterVR()`）
2. START メニュー表示 → 「START」押下
3. Stage 1〜7 進行（各ステージ: タイムリミット内に Bucchi を撃つ）
4. 全ステージクリア後 → リザルト画面（スコア・最大コンボ・ヒット数・ランキング表示）
5. 12 秒後にフェードアウト → VR 退出 → ページ再読込

### スコア計算
```
baseScore = max(5, round(50 × (1 - hitTime / stageLimit)))
comboMult = 1 + min(combo, 10) × 0.1
finalScore = baseScore × comboMult
```

### ステージ設定
| Stage | 時間 | 背景 | BGM |
|-------|------|------|-----|
| 1 | 10s | sky01 | bgm_s1 |
| 2 | 12s | sky02 | bgm_s1 |
| 3 | 12s | sky03 | bgm_s2 |
| 4 | 12s | sky04 | bgm_s2 |
| 5 | 12s | sky05 | bgm_s3 |
| 6 | 12s | sky06 | bgm_s3 |
| 7 | 12s | sky01 | bgm_s4（リザルト） |

### A-Frame コンポーネント
- `auto-enter-vr` — WebXR 検出 → 自動 VR 入場
- `vr-controller` — raycaster 依存（空コンポーネント）
- `start-menu` — ゲーム開始・ステージ進行・タイマー・モデル管理・フェード・リザルト・API 連携
- `shoot` — ボール発射（トリガー/クリック）・重力込み弾道・当たり判定・同時最大 2 発
- `hit-box` — ヒット検出 → anime02 切替 → フェードアウト → スコア計算 → 再出現

### パフォーマンス対策
- ボールオブジェクトプーリング（最大 4 発、発射上限 2 発）
- GPU リソース解放（`disposeEntityResources`）
- タイムアウト追跡（`registerTimeout` + `clearAllTimers`）
- 左コントローラー laser 非表示（`showLine: false`）
- 次ステージ背景/BGM プリロード
- tick スロットリング（100ms 間隔）

### findakaei からの改善点
| 問題 | 修正 |
|------|------|
| `OnStartButtonClick()` 未定義 | event listener（click / `triggerdown`） |
| `PassSec` 未初期化 | `Date.now()` ベースの `gameStartTime` |
| `setInterval` 文字列引数 | 関数リファレンス |
| CSRF なし | `<meta name="csrf-token">` + `X-CSRF-TOKEN` ヘッダ |
| `userid` ハードコード | `name: 'noName'` |
| 単一ファイル（~1500行） | 3 ファイル分割（index / _scene / _components） |
| axios 読み込み（未使用） | native `fetch` のみ |

### API 連携
- **スコア送信:** `POST /scores` — `{ game_name: 'findHoufu', score, rank, name: 'noName' }`
- **ランキング取得:** `GET /scores?game=findHoufu&limit=5`

### 必要アセット
- モデル: `public/cg/202609/model01_bucchi.glb`（anime01 / anime02 必須）
- BGM: `sound_bgm11.mp3` 〜 `sound_bgm14.mp3`
- 効果音: `sound_animal_appear.mp3` / `sound_animal_die.mp3`
- 背景: `R0010095.JPG` 〜 `R0010143.JPG`（6 種）
- 銃: `gun_01.glb`
- ボール: `poke_ball_02orange.glb`

### 操作
- **VR:** 右コントローラー Trigger で射撃
- **PC:** Space キー / マウスクリック で射撃

### テストチェックリスト
- [ ] `/findHoufu` でページロード
- [ ] START メニュー表示
- [ ] VR 自動入場（WebXR 対応ブラウザ）
- [ ] Stage 1 開始（sky01 + bgm_s1）
- [ ] モデル出現（anime01 + 出現音）
- [ ] ボール射撃 → ヒット → anime02 → フェードアウト → 再出現
- [ ] スコア更新・コンボ表示
- [ ] Stage 遷移（背景 + BGM 切替 + フェード）
- [ ] 7 ステージクリア → リザルト画面
- [ ] スコア POST / ランキング GET
- [ ] タイムアウト時（12 秒超 → スコア 0 + コンボリセット）

---

## terrer4に関するメモ
発射ポケボールの速度はここです。
_components.blade.php:1075

multiplyScalar(40) の 40 が初速です。
例えば速くするなら 50、遅くするなら 30 にします。
あわせて、弾道の落下量はここです。
_components.blade.php:956

- 2.45 * elapsedTime * elapsedTime の 2.45 が重力係数です。
ここを大きくすると早く落ち、小さくするとまっすぐ飛びやすくなります。





敵の再生成間隔は主に2か所です。

撃破後の再生成待ち時間（今は4秒）
_components.blade.php:1317
ここが }, 4000); になっていて、倒してから再生成までの待機です。
approach-camera の待機時間パラメータ（waitTime）
判定実行: _components.blade.php:1109
初期スポーン時の設定値（4秒）: _components.blade.php:576
再生成時の設定値（4秒）: _components.blade.php:1341
実際に体感に効くのはまず _components.blade.php:1317 です。ここを増やすと再生成が遅くなります。


## shooting3Dterrer4 更新（2026-06-09）

- ゲーム中でも Grip / A / B ボタンで武器切替できるように変更
- ステージ時間を変更
   - Stage1: 100秒
   - Stage2: 80秒
   - ボス出現条件は残り15秒のまま
- 残弾システムを追加
   - 初期弾数: Gun1=20, Gun2=20
   - 残弾0の武器は切替可能、発射のみ不可
   - 画面右下に Gun1/Gun2 の残弾HUDを常時表示（VR視点追従）
   - アイコン: `public/cg/pokeball_icon05.png`, `public/cg/pokeball_icon06.png`
- 弾薬補給演出を追加
   - `#model_s1_01` 撃破時: 補給ボールがカメラへ飛来し、未使用武器へ +5
   - `#model_s2_01` 撃破時: 補給ボールがカメラへ飛来し、未使用武器へ +10
   - 加算は補給ボール到達時に実施
- Stage2 終了後の GameOver ボタンは、ページ終了ではなく VR モード解除のみ実施

## shooting3Dterrer4 VR負荷改善（2026-06-09 追記）

- Stage2 の通常敵の同時出現数を 4 体に制限
   - 初期スポーン対象を 5 体ではなく 4 体に制御
- ボールを簡易オブジェクトプール方式へ変更
   - 発射ごとの `create/remove` を抑制し、Gunごとに再利用
   - 命中時・寿命切れ・ステージ遷移・GameOver時はプール返却へ統一

## ARworkshop01 更新（2026-06-25）

- `resources/views/ARworkshop01.blade.php` を新規作成
  - `/t-watanabe` でアクセス可能な AR 画面
  - `cg/202606/pattern-maker00.patt` 〜 `pattern-maker20.patt` を認識し、対応する `AnimePistol_Textured_00081_.glb` 〜 `AnimePistol_Textured_00101_.glb` を表示
  - 3D モデルは `anime01` / `anime02` を再生せず、表示のみ
  - ピンチ操作で拡大縮小対応
  - Z軸回転による自動回転を追加
- 6桁のパスコード入力を追加
  - 正しいコードは `385252`
  - 誤入力の場合は 3D モデルを表示しない
- 検索エンジン除外対策を追加
  - `ARworkshop01` ページの `<meta name="robots" content="noindex,nofollow">` を追加
  - `/t-watanabe` ルートに `X-Robots-Tag: noindex, nofollow` ヘッダーを付与
  - `public/robots.txt` に `/t-watanabe` を Disallow 追加

# ARstampRally202603 にかかわるTodo
　・サンタクロース　→　キリン　（スタンプ帳が未対応）
　・キリン以外はシークレット
　・ヒントマップが修正できてない箇所あり
　・https://seiei.tech/dx-2025　へのリンク（活動を紹介）
　・

---

### ARstampRally202603 - Androidズーム修正・UI左上集約・カメラ切替削除・任意タップ投げ・投擲後自動GET 20260501

**ARstampRally202605 で実施した改善内容を ARstampRally202603 単一ファイル版にも適用:**

#### 変更内容

**1. ガイドモーダルを起動時に非表示**
- `window.guideModalOpen = false;` に変更（起動時モーダル表示ブロックを削除）

**2. Androidズーム修正**
- `video { object-fit: contain !important; }` と `a-scene canvas { object-fit: contain !important; width: 100% !important; height: 100% !important; }` を CSS に追加
- `<a-entity camera>` → `<a-entity camera look-controls="enabled: false">` に変更

**3. UIボタン配置変更（左上集約）・カメラ切替削除**
- カメラ切替ボタン（🔄）を HTML・CSS・JS すべてから削除
- スタンプ帳・ガイド・静止画撮影・動画撮影の4ボタンを `#top-left-buttons` フレックスコンテナ（画面左上横並び）に集約
- 各ボタンの個別 `position: fixed` CSS ブロックを削除し、共通スタイル（`#top-left-buttons button`）に一本化
- 投げボタン（`#throw-button`）の `display: none !important;` を削除（表示を復元）

**4. 任意タップ投げ（ボタン＋どこでもタップ両対応）**
- 既存のスワイプ/タップ投げロジックをそのまま維持
- 投げるボタンを復活させ、ボタンタップでも投げられるように

**5. 投擲1秒後に自動GET（マーカー検出時のみ）**
- `pokeball-throwable` コンポーネントに `autoGetStampId` / `autoGetDelayMs` スキーマを追加
- `throw()` 内で1秒タイマーを設定、`tryAutoGet()` で `collectAndMarkWithRetry` / `showCapturedMessage` を実行
- `handleHit()` でタイマーをキャンセルして二重処理を防止
- `getActiveVisibleStampId()` 関数を追加し、可視ヒットボックスの `stampId` を取得して投擲属性に渡す

#### 変更ファイル
- `resources/views/ARstampRally202603.blade.php`（単一ファイル、7000行超）

#### 動作確認
- `php -l` 構文エラーなし

# ARstampRally202605 にかかわるTodo
　・model_01〜model_10 の GLBファイルを `public/cg/202605/Model_01.glb` 〜 `Model_10.glb` に配置すること
　・マーカーパターンファイルを `public/cg/202605/` に配置すること (pattern-maker00.patt, pattern-maker01.patt 〜 pattern-maker10.patt)
　・STAMPS オブジェクトのモデル名・アイコンを実際のキャラクター名に合わせて更新すること (`resources/views/ARstampRally202605/js-stamps.blade.php`)
　・景品交換閾値: 5 (PRIZE_EXCHANGE_THRESHOLD = 5) ← 6から変更済み
　・ギャラリー(maker00): 捕獲済み選択モデル最大5体をY軸方向に並べて表示 (GALLERY_Y_SPACING = 0.35)
　・投げ方式: タップ即投げ（HUDスワイプなし）

---

### ARstampRally202605 - 投擲1秒後の自動GET（案B） 20260421

**ズーム問題が残る端末でも捕獲を成立させるため、投擲から1秒後に自動GETする仕様を追加:**

#### 仕様
- ボールを投げる演出は従来どおり維持
- マーカー検出中の対象がいる場合のみ、投擲1秒後に自動GET
- 実ヒットが先に発生した場合は、遅延自動GETをキャンセルして二重処理を防止

#### 変更内容（案B）
- `resources/views/ARstampRally202605/aframe-components.blade.php`
   - `pokeball-throwable` に `autoGetStampId` / `autoGetDelayMs` を追加
   - `throw()` 内で1秒タイマーを設定し `tryAutoGet()` を実行
   - `handleHit()` でタイマーを停止して競合を回避
- `resources/views/ARstampRally202605/js-throw.blade.php`
   - 可視ヒットボックスから `stampId` を取得する `getActiveVisibleStampId()` を追加
   - 投擲時に `autoGetDelayMs: 1000` と `autoGetStampId` を `pokeball-throwable` へ連携

---

### ARstampRally202605 - look-controls 無効化（Androidボール方向ずれ・ページズーム修正）20260421

**Android（moto g64y 5G）でボールが明後日の方向に飛ぶ問題・ページ全体がズームされる問題を修正:**

#### 根本原因
A-Frame は `<a-entity camera>` に `look-controls` コンポーネントを自動付加する。  
Android Chrome では `look-controls` が DeviceOrientationEvent（ジャイロセンサー）を無許可で受信し、  
`scene.camera.quaternion` をデバイスの物理的な向きで上書きしていた。

- **ボール方向ずれ**: ボール投げ方向の計算が `camera.quaternion` に依存するため、AR追跡と無関係な方向に飛ぶ
- **ページズーム**: `look-controls` のタッチハンドラが 2本指タッチイベントの伝搬を遮断し、document 側のズーム防止ハンドラが機能しなかった

#### 変更内容
- `resources/views/ARstampRally202605/scene.blade.php`: `<a-entity camera>` → `<a-entity camera look-controls="enabled: false">` （1行変更）

---

### ARstampRally202605 - Androidズーム修正・UI左上集約・カメラ切替削除 20260421

**Androidカメラ映像ズーム問題（moto g64y 5G）の修正とUI配置変更:**

#### 変更内容

**1. Androidズーム修正（最小修正案）**
- AR.js設定の二重適用を解消: `js-init.blade.php` 冒頭の Android 向け `setAttribute('arjs', ...)` 上書きブロックを削除し、`scene.blade.php` の設定を唯一のソースに一本化
- AR最終描画（canvas）へのズーム抑制CSS追加: `video` のみだった `object-fit: contain` を `a-scene canvas` にも追加
- ピンチズーム機能を無効化: `scene` に登録していた `touchstart/touchmove/touchend` によるモデル拡縮処理を削除（PCホイールは維持）

**2. UIボタン配置変更**
- カメラ切替ボタン（🔄）を削除
- スタンプ帳・ガイド・動画撮影・静止画撮影の4ボタンを画面左上に横並びで表示
- 個別の `position: fixed` を廃止し `#top-left-buttons` フレックスコンテナに集約

#### 変更ファイル
- `resources/views/ARstampRally202605/js-init.blade.php`: Android arjs 上書き削除・ピンチ処理削除（ホイールのみ維持）
- `resources/views/ARstampRally202605/head.blade.php`: `a-scene canvas` ズーム抑制CSS追加・ボタンCSS左上横並びに再構成
- `resources/views/ARstampRally202605/ui.blade.php`: カメラ切替ボタン削除・4ボタンを `#top-left-buttons` コンテナにまとめ順序変更

---

### ARstampRally202605 - スタンプ帳閉じた後にギャラリー表示が更新されない問題を修正 20260418

**スタンプ帳でギャラリー選択を変更しても、閉じた後にギャラリーが更新されなかった問題を修正:**

#### 原因
- `onMarkerConfirmed()`はIIFE内のクロージャで定義されており、外部から呼び出せなかった
- スタンプ帳を閉じるハンドラはモーダルを非表示にして`resumeCamera()`を呼ぶだけで、ギャラリー再描画を行っていなかった

#### 修正内容
1. `js-gallery.blade.php`: `onMarkerConfirmed`を`window.refreshGallery`として公開（+1行）
2. `js-init.blade.php`: スタンプ帳を閉じる2箇所（×ボタン・背景クリック）に`window.refreshGallery()`呼び出し追加（+2行）

#### 変更ファイル
- `resources/views/ARstampRally202605/js-gallery.blade.php`: +1行
- `resources/views/ARstampRally202605/js-init.blade.php`: +2行

#### 動作確認済み項目
- ✅ PHP構文エラーなし（2ファイル）
- ✅ スタンプ帳でギャラリー選択変更→閉じる→ギャラリーが即更新される
- ✅ マーカーが見えていない場合は`markerVisible`ガードで安全にスキップ

---

### ARstampRally202605 - iPhone SEフリーズ対策・Model_00追加・ギャラリー選択機能 20260418

**maker00（ギャラリーマーカー）読み取り時にiPhone SEがフリーズする問題と複数機能追加:**

#### 主な変更点

1. **景品交換閾値を6→5に変更**
   - `PRIZE_EXCHANGE_THRESHOLD = 5`（`js-prize.blade.php`）

2. **Model_00 追加・アニメーション切替**
   - `scene.blade.php`: marker-00内にModel_00エンティティ（id="model-00", position="1.1 0 0.5", scale="0.6 0.6 0.6"）を追加
   - `gltf-model`ではなく`lazy-model`を使用（maker00検出時のみロード）
   - 捕獲数に応じてアニメーション自動切替: 0〜4体→anime01、5〜9体→anime02、10体→anime03
   - アニメーション再生スピードを通常の半分（timeScale=0.5）に設定

3. **rAFループ最適化（iPhone SEフリーズ根本修正）**
   - js-gallery.blade.php の独立rAFループを削除（js-initのループと二重になっていた）
   - `window.startGalleryMixerLoop()` / `window.stopGalleryMixerLoop()` をjs-initで公開
   - maker00 markerFound時に起動、markerLost時に完全停止（CPU負荷ゼロ）

4. **Model_00のlazy-model化**
   - marker-00未検出時はGLBをメモリにロードしない
   - `model-unloaded`イベントでmixer/actions/currentClipを完全クリーンアップ

5. **ギャラリー表示を最大5体に制限**
   - `onMarkerConfirmed()`が`getGallerySelection()`を参照（全捕獲済みではなく選択済みのみ）
   - 未選択モデルはロード/表示しない。キャッシュ済みの未選択モデルはvisible=false

6. **ギャラリー選択機能（スタンプ帳）**
   - LocalStorageキー `ar-gallery-selection-202605` で最大5体の選択IDを管理
   - `getGallerySelection()`: 選択済み最大5体を返す（未設定時は捕獲日時順の先頭5体にフォールバック）
   - `saveGallerySelection()` / `toggleGallerySelection()`: 選択の保存・ON/OFFトグル
   - スタンプ帳の捕獲済みスタンプをタップしてギャラリー表示するモデルを選択可能
   - 選択中のスタンプには緑のボーダー＋チェックマーク（✓）バッジを表示

#### 変更ファイル
- `resources/views/ARstampRally202605/js-prize.blade.php`: PRIZE_EXCHANGE_THRESHOLD 6→5
- `resources/views/ARstampRally202605/scene.blade.php`: Model_00エンティティ追加（lazy-model）
- `resources/views/ARstampRally202605/js-gallery.blade.php`: 全面改修（rAFループ削除、Model_00管理、ギャラリー5体制限）
- `resources/views/ARstampRally202605/js-init.blade.php`: startGalleryMixerLoop/stopGalleryMixerLoop公開
- `resources/views/ARstampRally202605/js-stamps.blade.php`: ギャラリー選択関数3つ追加、showStampBook修正
- `resources/views/ARstampRally202605/head.blade.php`: `.gallery-check` / `.gallery-selected` CSS追加

#### 動作確認済み項目
- ✅ PHP構文エラーなし（全6ファイル）
- ✅ rAFループ重複なし（markerLost時にCPU負荷0）
- ✅ ギャラリーは選択済み最大5体のみ表示
- ✅ スタンプ帳でギャラリー表示モデルを選択可能
- ✅ Model_00がanime01/02/03を捕獲数に応じて切替
- ✅ 景品交換閾値5個で動作

---

### ARstampRally202605 - ギャラリーマーカーiPhone SEフリーズ修正 20260417

**maker00（ギャラリーマーカー）読み取り時にiPhone SEがフリーズする問題を修正:**

#### 原因
- `markerFound` 時に捕獲済み全モデル（最大10体）のGLBを同時ロードしていた
- `markerLost` のたびに全エンティティを `removeChild` で破棄 → 再検出時に再ロード（GC + GPU再アップロードで詰まる）
- `markerFound/Lost` の高速フリッカー（AR.jsの検出不安定）が上記を繰り返し発生させていた
- `AnimationMixer.update()` が呼ばれておらずアニメーションも正常再生されていなかった

#### 修正内容（3対策を統合）

1. **キャッシュ**: `markerLost` でエンティティを破棄せず `visible=false` のみ。再検出時は `visible=true` で即表示（再ロードなし）
2. **デバウンス 300ms**: `markerFound` 発火から300ms以内の再発火を無視。高速フリッカーによる繰り返し処理を防止
3. **逐次ロード 500ms間隔**: 未キャッシュモデルを同時生成せず1体ずつ500ms間隔でロード。`markerLost` 時にキュー中断

#### 追加修正
- `requestAnimationFrame` で全 `AnimationMixer` を毎フレーム `update()` するループを追加（アニメーション正常再生）

#### 変更ファイル
- `resources/views/ARstampRally202605/js-gallery.blade.php`: 全面書き換え（89行 → 138行）

#### 動作確認済み項目
- ✅ PHP構文エラーなし
- ✅ markerLost→markerFound高速切り替えで再ロードが走らない（キャッシュ）
- ✅ 初回のみ逐次ロード（500ms間隔）
- ✅ 既存の捕獲・投擲機能への影響なし

---

### ARstampRally202605 - 「捕まえました！」モーダル自動クローズ 20260417

**ボール命中後にモーダルが消えないことがある問題を修正:**

#### 修正内容
- `showCapturedMessage()` に1.5秒後の自動クローズタイマーを追加
- 連続ヒット時は前のタイマーをキャンセルして1.5秒リセット

#### 変更ファイル
- `resources/views/ARstampRally202605/js-stamps.blade.php`: `showCapturedMessage` 関数に `setTimeout` 追加

---

# ARstampRally202606 にかかわるTodo
　・model_00〜model_20 の GLBファイルを `public/cg/202606/Model_00.glb` 〜 `Model_20.glb` に配置すること
　・マーカーパターンファイルを `public/cg/202606/` に配置すること (pattern-maker00.patt, pattern-maker01.patt 〜 pattern-maker20.patt)
　・STAMPS オブジェクトのモデル名・アイコンを実際のキャラクター名に合わせて更新すること (`resources/views/ARstampRally202606/js-stamps.blade.php`)
　・景品交換閾値: 10 (PRIZE_EXCHANGE_THRESHOLD = 10)
　・ギャラリー(maker00): Model_00固定表示 + 捕獲済み選択モデル最大4体を東西南北4方向に配置
　・投げ方式: タップ即投げ（ARstampRally202605と同じ）

---

### ARstampRally202606 - 新規作成 (ARstampRally202605ベース) 20260520

**ARstampRally202605をベースに2026年6月イベント向け新版を作成:**

#### 主な変更点（202605との差分）

**1. スタンプ数: 10種 → 20種**
- `STAMPS`: `model_01` 〜 `model_20`（20種）
- `TOTAL_STAMP_SLOTS = 20`
- マーカー: marker-01〜marker-20（scene.blade.phpのループ上限を10→20に変更）

**2. ギャラリー仕様変更（大幅変更）**
- 202605: 捕獲済み5体をY軸方向に並べて表示（`GALLERY_Y_SPACING`）
- 202606: **Model_00を(0,0,0)に固定表示** + 捕獲済み選択4体を東西南北に配置
  ```
  GALLERY_POSITIONS = [
      { x:0, y:0, z:1 },   // 南
      { x:0, y:0, z:-1 },  // 北
      { x:1, y:0, z:0 },   // 東
      { x:-1, y:0, z:0 }   // 西
  ]
  ```
- `GALLERY_MAX_DISPLAY = 4`（202605は5）

**3. gallery-hitbox コンポーネント（新規）**
- 202605の`hitbox`はスタンプ取得フローに直結していたため、ギャラリー専用コンポーネントを新設
- `window.allGalleryHitboxes[]` に登録・除去（`window.allHitboxes`とは独立）
- `pokeball-throwable`のtick()でallGalleryHitboxesをループ → `handleGalleryHit()` → `window.playGalleryHitAnimation()` を呼ぶのみ（スタンプ取得なし）

**4. ギャラリーヒットアニメーション（window.playGalleryHitAnimation）**
- anime01停止 → anime02一度再生 → 完了後anime01ループに戻す
- AnimationMixer の `finished` イベント + タイムアウト保険で確実にanime01へ復帰

**5. 景品交換閾値: 5 → 10**
- `PRIZE_EXCHANGE_THRESHOLD = 10`
- ガイドテキスト: 「6種類以上」→「11種類以上」

**6. LocalStorage/Cookieキーをすべて202606に変更**
- `ar-stamp-rally-202606`
- `ar-captured-animals-202606`
- `ar-gallery-selection-202606`
- `ar-user-id-202606` / `ar_user_id_202606`（Cookie）
- `ar-prize-exchanged-202606`
- `ar-prize-code-202606`
- `marker-scan-cache-202606-*`

**7. IndexedDB/UUID/Cookieクラス名を202606に変更**
- `UserIdDB202606` / `CookieHelper202606` / `generateUUID202606()` / `getUserId202606()`
- DBname: `ARStampRallyDB202606`

**8. 管理ダッシュボード集計期間**
- `2026-05-20 00:00:00 JST` 〜 `2026-06-10 23:59:59 JST`

#### 新規作成ファイル（11ファイル）
- `resources/views/ARstampRally202606.blade.php`: エントリポイント（`@include`で10モジュール読込）
- `resources/views/ARstampRally202606/head.blade.php`: HEADタグ・CSS・グローバル変数（`window.allGalleryHitboxes = []` 追加）
- `resources/views/ARstampRally202606/ui.blade.php`: モーダル・ボタン等HTML（`total-slots`初期値20）
- `resources/views/ARstampRally202606/scene.blade.php`: a-sceneとマーカー定義（maker00+maker01-20）、`id="model-00"` エンティティ追加
- `resources/views/ARstampRally202606/aframe-components.blade.php`: `gallery-hitbox`コンポーネント追加、`pokeball-throwable`に`handleGalleryHit`追加
- `resources/views/ARstampRally202606/js-stamps.blade.php`: STAMPS 20種定義、ギャラリー選択4体管理
- `resources/views/ARstampRally202606/js-prize.blade.php`: 景品交換（閾値10）、202606固有キー
- `resources/views/ARstampRally202606/js-throw.blade.php`: ARstampRally202605と同一（コピー）
- `resources/views/ARstampRally202606/js-gallery.blade.php`: ギャラリー機能（4方向配置、Model_00固定、playGalleryHitAnimation）
- `resources/views/ARstampRally202606/js-camera.blade.php`: ARstampRally202605と同一（コピー）
- `resources/views/ARstampRally202606/js-init.blade.php`: DOMContentLoaded初期化（ループ上限20、202606キー、ガイドテキスト更新）

#### 変更ファイル（3ファイル）
- `app/Http/Controllers/AdminController.php`: `dashboard202606()`メソッド追加（model_01〜model_20の20種、集計期間202606）
- `resources/views/admin/dashboard202606.blade.php`: dashboard202605.blade.phpをベースに「202605」→「202606」に変更
- `routes/web.php`: `GET /stamp202606` と `GET /admin/dashboard202606` を追加

#### 動作確認済み項目
- ✅ PHP構文エラーなし（AdminController.php, routes/web.php）
- ✅ 202605の機能に影響なし（キー・クラス名がすべて独立）
- ✅ gallery-hitboxはallHitboxesと完全分離

---

### ARstampRally202609 - カメラ射影同期・モデルスケール修正・補助修正 20260902

**ARstampRally202606 の分析で特定され未修正のままだった問題を修正（検証結果: `resources/views/ARstampRally202609/analysis_qwen3.8.md`、要件/設計/タスク: `.claude_workflow/ar_requirements.md` / `ar_design.md` / `ar_tasks.md`）**

**1. AR.js 射影パラメータを実態に同期（全方向）**
- `js-init.blade.php` に `syncArjsToRealSize()` を新設: `arjs-video-loaded`（動画読み込み完了）時に `source=動画実寸 (videoWidth/Height)` / `display=実画面 (innerWidth/Height)` に再設定
- `resize`（200ms）/ `orientationchange`（300ms）でもデバウンス付きで再同期
- `desiredMaxDetectionRate()`: 現行の `maxDetectionRate` を維持（低解像度リトライの8は維持）。低スペックモード（`AR_FORCE_LOWRES`）では最大8
- 従来の縦型限定ブロック（`vid.videoWidth < vid.videoHeight` ガード）を全方向同期に置換

**2. 初期値の統一・UA分岐と Samsung 専用パッチの削除**
- `scene.blade.php`: Android 640×480（4:3）出し分けを廃止し、全端末 `1280×720`（16:9）の暫定値に
  - 低解像度 `ideal` 制約による Android カメラ HAL のデジタルクロップも回避
  - `arjs-video-loaded` 後立即に実寸へ同期するため、縦型ストリームへの影響はない
- Samsung Galaxy 縦型 480×640 上書きの `<script>` ブロックを削除（1. が代替）

**3. モデルの二重スケール（base²）解消**
- `applyCurrentScaleTo()`: メッシュへの `node.scale.set(v,v,v)` を削除
- 世界スケールはルート（A-Frame `scale` = baseScale × currentScale）の1回分のみ
- `traverse` はメッシュへの `frustumCulled = false` 設定に限定
- wheel/ピンチズーム2倍がモデルも2倍になる（従来は4倍相当）

**4. 低解像度リトライ時の display 更新**
- 「低解像度で再試行」で `displayWidth/Height` を実画面サイズに設定（従来は未設定でスケールがずれる主因C）

**5. 低スペック判定の精緻化（AR_FORCE_LOWRES）**
- `head.blade.php`: 従来の「Android 7以下」・`?lowres=1` URLパラメータに加え、Android の `hardwareConcurrency <= 2` または `deviceMemory <= 2` を判定
  - `navigator` 非対応環境（0/undefined）は判定しない（従来挙動を維持）
- 1. の `desiredMaxDetectionRate()` 経由で検出レート（30→8）に実効化（カメラ解像度は手動の低解像度リトライ経路を維持）

**6. MediaRecorder feature detection**
- `js-camera.blade.php`: `MediaRecorder` 未実装環境（iOS 14.2以前等）では動画ボタンを非表示に（タップ後のエラー表示ではなく事前隠蔽）

**7. 景品交換の二重送信防止**
- `js-prize.blade.php`: `exchangePrize()` に `_exchanging` ガードを追加（全4終端パスで復元。ボタンの disabled 操作はせず `updatePrizeButton()` との状態衝突を回避）

#### 変更ファイル（6ファイル）
- `resources/views/ARstampRally202609/scene.blade.php`: 初期値統一・Samsungブロック削除
- `resources/views/ARstampRally202609/js-init.blade.php`: `syncArjsToRealSize` / スケール / 低解像度 display
- `resources/views/ARstampRally202609/head.blade.php`: 低スペック判定・CSSコメント修正
- `resources/views/ARstampRally202609/js-camera.blade.php`: MediaRecorder 判定
- `resources/views/ARstampRally202609/js-prize.blade.php`: 二重送信ガード
- `README.md`: 本ドキュメント

#### 確認済み項目
- ✅ `php -l` で構文エラーなし（変更した blade ファイル5ファイルすべて）
- ⏳ 実機検証（Android縦長/横長のカメラ比率・モデル位置、Samsung縦型の読み込み直後認識、iOS回帰、低スペック機、`MediaRecorder` 非対応環境で動画ボタン非表示）— 開発者実行予定

---

### ARstampRally202605 - 新規作成 (モジュール化リファクタリング)

**ARstampRally202603をベースに2026年5月イベント向け新版を作成:**

**新機能・変更点:**
- タップ投げ方式（HUDポケボール画像・スワイプ廃止）
- maker00をギャラリーマーカーとして使用（捕獲済みモデルをY軸方向に並べて表示）
- 景品交換閾値: 10→6
- Androidカメラズーム修正 CSS (`video { object-fit: contain !important; }`)
- 11ファイルのモジュール構成

**新規ファイル:**
- `resources/views/ARstampRally202605.blade.php`: エントリポイント
- `resources/views/ARstampRally202605/head.blade.php`: HEADタグ・CSS・グローバル変数
- `resources/views/ARstampRally202605/aframe-components.blade.php`: pokeball-throwable, hitbox, lazy-model, click-animation
- `resources/views/ARstampRally202605/scene.blade.php`: a-sceneとマーカー定義 (maker00+maker01-10)
- `resources/views/ARstampRally202605/ui.blade.php`: モーダル・ボタン等のHTML
- `resources/views/ARstampRally202605/js-stamps.blade.php`: STAMPS定義、LocalStorage管理
- `resources/views/ARstampRally202605/js-prize.blade.php`: IndexedDB/Cookie、景品交換API
- `resources/views/ARstampRally202605/js-throw.blade.php`: タップ投げ実装
- `resources/views/ARstampRally202605/js-gallery.blade.php`: maker00ギャラリー機能
- `resources/views/ARstampRally202605/js-camera.blade.php`: 写真・動画撮影
- `resources/views/ARstampRally202605/js-init.blade.php`: DOMContentLoaded初期化
- `app/Http/Controllers/AdminController.php`: `dashboard202605()`メソッド追加
- `resources/views/admin/dashboard202605.blade.php`: 管理ダッシュボード（2026年5月統計）

**追加ルート (`routes/web.php`):**
- `GET /stamp202605` → `ARstampRally202605` ビュー
- `GET /admin/dashboard202605` → `AdminController@dashboard202605`

# 変更点

### ARスタンプラリー202603 - dashboard202603のページネーションアイコンサイズ修正 20260219
**管理画面のページネーションアイコンサイズを統一:**

#### 修正内容
- 景品交換セクション（未使用・使用済み）のページネーションアイコンを18px × 18pxに修正
- `.pagination-wrapper svg`と`.pagination-wrapper nav svg`のCSSスタイルを追加
- すべてのページネーションアイコンのサイズを統一

#### 変更ファイル
- `resources/views/admin/dashboard202603.blade.php`: CSSスタイル追加（11行）

#### 技術的詳細
- `.pagination-wrapper svg`と`.pagination-wrapper nav svg`セレクターを追加
- `!important`でTailwind CSSクラス（w-5 h-5）を上書き
- `width`、`height`、`max-width`、`max-height`を18pxに設定
- 既存の`.pagination svg`スタイルとの一貫性を保つ

#### 動作確認済み項目
- ✅ 未使用景品交換のページネーションアイコンが18px × 18px
- ✅ 使用済み景品交換のページネーションアイコンが18px × 18px
- ✅ スキャン履歴のページネーションへの影響なし
- ✅ すべてのページネーションアイコンのサイズが統一
- ✅ レスポンシブデザインの維持
- ✅ 構文エラーなし

#### 設計ドキュメント
- 要件定義9: `.claude_workflow/requirements.md`
- 設計9: `.claude_workflow/design.md`
- タスク化9: `.claude_workflow/tasks.md`

---

### ARスタンプラリー202603 - dashboard202603に景品交換統計を追加 20260219
**admin/dashboard202603の管理画面に景品交換の統計情報と一覧を追加:**

#### 実装内容
1. **景品交換統計カードの追加**
   - 総景品交換数、使用済み数、未使用数を表示する3枚のカード
   - ヘッダー直後、動物別統計の前に配置
   - レスポンシブ対応（1200px以下で縦並び）

2. **未使用の景品交換セクション**
   - 景品コード検索機能（部分一致、大文字小文字区別なし）
   - 未使用景品交換一覧（20件/ページ、ページネーション）
   - 「使用済みにする」ボタン（AJAX処理）

3. **使用済み景品交換セクション**
   - 使用済み景品交換一覧（10件/ページ、ページネーション）
   - 使用日時の表示（緑色）
   - 景品コードのグレーアウト表示

4. **JavaScript機能**
   - redeemPrize(id)関数（AJAX POST通信）
   - 確認ダイアログ
   - CSRF token送信
   - 完了後のページリロード

#### 変更ファイル
- `app/Http/Controllers/AdminController.php`: dashboard202603()メソッド拡張（景品交換データ取得を追加）
- `resources/views/admin/dashboard202603.blade.php`: CSS、HTML、JavaScript追加

#### 技術的詳細
- データベースクエリ: 景品交換統計（2クエリ）、未使用景品交換（2クエリ）、使用済み景品交換（2クエリ）
- ページネーション: 異なるクエリパラメータ名で競合回避（exchanges_page, redeemed_page）
- 検索機能: GET param `q`で景品コード検索（部分一致、大文字小文字区別なし）
- セキュリティ: CSRF保護、認証、SQLインジェクション対策、XSS対策
- レスポンシブ: 1200px以下で1カラムレイアウトに変更

#### 動作確認済み項目
- ✅ 景品交換統計カードが表示される（3枚）
- ✅ 未使用の景品交換一覧が表示される（検索機能、ページネーション）
- ✅ 使用済み景品交換一覧が表示される（ページネーション）
- ✅ 「使用済みにする」ボタンが機能する（AJAX処理）
- ✅ ページネーションの独立性が保たれる
- ✅ 既存機能への影響なし
- ✅ 30秒ごとの自動更新が機能する
- ✅ PHP構文エラーなし

#### 設計ドキュメント
- 要件定義8: `.claude_workflow/requirements.md` (要件定義8セクション)
- 設計8: `.claude_workflow/design.md` (設計8セクション)
- タスク化8: `.claude_workflow/tasks.md` (タスク化8セクション)

---

### ARスタンプラリー202603 - dashboard202603のUI改善と日別個別ユーザー数統計追加 20260219
**admin/dashboard202603の管理画面を改善:**

#### 実装内容
1. **ページネーションUI修正**
   - SVGアイコンのサイズを18px × 18pxに制御
   - ボタンの視覚的なバランスを改善
   - 中央揃えとサイズ統一
   - !importantルールでLaravelデフォルトを上書き

2. **日別個別ユーザー数統計の追加**
   - 直近30日間の日別ユニークユーザー数をグラフ化
   - マーカー検出とボールヒット別に集計
   - Chart.jsで折れ線グラフとして表示
   - ホバー時にツールチップで詳細表示
   - データがない日は0として表示

#### 変更ファイル
- `app/Http/Controllers/AdminController.php`: dashboard202603()メソッド拡張（日別個別ユーザー数クエリ追加）
- `resources/views/admin/dashboard202603.blade.php`: CSS、HTML、JavaScript追加

#### 技術的詳細
- SQLクエリ: `COUNT(DISTINCT fingerprint)`で日別ユニークユーザー数を取得
- データ期間: 固定で直近30日間
- グラフタイプ: Chart.js Line Chart
- 色使い: マーカー検出（青: rgba(52, 152, 219, 1)）、ボールヒット（赤: rgba(231, 76, 60, 1)）
- 折れ線の透明度: 0.1（背景）
- ポイント半径: 4px（通常）、6px（ホバー）
- 曲線テンション: 0.3（なめらか）

#### 動作確認済み項目
- ✅ ページネーションのSVGアイコンが18px × 18pxで表示される
- ✅ 日別個別ユーザー数グラフが表示される
- ✅ グラフが2つのライン（マーカー検出/ボールヒット）で表示される
- ✅ ホバー時にツールチップで数値が表示される（「○○人」）
- ✅ データがない日は0として表示される
- ✅ 既存の統計表示に影響なし
- ✅ PHP構文エラーなし

#### 設計ドキュメント
- 要件定義7: `.claude_workflow/requirements.md` (要件定義7セクション)
- 設計7: `.claude_workflow/design.md` (設計7セクション)
- タスク化7: `.claude_workflow/tasks.md` (タスク化7セクション)

---

### ARスタンプラリー202603 - マーカー検出とボールヒットの統計分離 20260219
**ARマーカー検出とボールヒットを区別して統計を記録:**

#### 実装内容
1. **データベース拡張**
   - marker_scansテーブルにcapture_typeカラムを追加（'marker_scan' または 'ball_hit'）
   - 既存データは'ball_hit'として扱う
   - capture_typeにインデックスを追加（検索パフォーマンス向上）

2. **マーカー検出の記録（新機能）**
   - markerFoundイベント時に記録（未捕獲の動物のみ）
   - 同じ端末・同じマーカー・同じ日付の重複は記録しない
   - LocalStorageキャッシュで当日の重複を防止（`marker-scan-cache-202603-{markerId}-{date}`）
   - サーバー側でも日付ベースの重複チェック実装

3. **ボールヒットの記録（既存機能の拡張）**
   - collectStamp関数でrecordMarkerScan呼び出し時にcapture_type: 'ball_hit'を明示
   - 新規ゲット時のみ記録（既存ロジック維持）

4. **ダッシュボード拡張（admin/dashboard202603）**
   - 全20種類の動物の統計を表示（通常15種 + シークレット5種）
   - 各動物ごとにマーカー検出回数とボールヒット回数を表示
   - タイプ別の色分け表示（マーカー検出: 青、ボールヒット: 赤）
   - Chart.jsで積み上げ棒グラフを表示（日別統計、タイプ別）
   - ユニークユーザー数（fingerprint別）を表示
   - 最近のスキャン履歴にタイプ（マーカー検出/ボールヒット）を表示

#### 変更・追加ファイル
- `database/migrations/2026_02_19_144419_add_capture_type_to_marker_scans_table.php`: 新規マイグレーション
- `app/Models/MarkerScan.php`: fillable配列にcapture_type追加
- `app/Http/Controllers/MarkerScanController.php`: record()メソッド拡張
  - captureTypeパラメータを受け取る
  - marker_scanの場合、当日の重複チェック実装
  - タイプ別にscan_countを集計
- `resources/views/ARstampRally202603.blade.php`: 4箇所修正、1関数新規
  - recordMarkerDetection関数追加（2788行目付近）
  - recordMarkerScan関数にcaptureTypeパラメータ追加（2818行目）
  - markerFoundイベントリスナーにrecordMarkerDetection呼び出し追加（773行目付近）
  - collectStamp関数でrecordMarkerScan呼び出し時にcaptureType: 'ball_hit'を指定（143行目）
- `app/Http/Controllers/AdminController.php`: dashboard202603()メソッド拡張
  - 全20種類の動物の統計を取得
  - タイプ別（marker_scan, ball_hit）にカウント
  - 日別統計をタイプ別に取得
- `resources/views/admin/dashboard202603.blade.php`: 全面的に書き直し
  - 動物別統計テーブル追加（マーカー検出回数、ボールヒット回数、合計、ユニークユーザー数、最終スキャン）
  - 最近のスキャン履歴にタイプ表示追加
  - Chart.jsで積み上げ棒グラフ追加（日別統計、タイプ別）

#### 技術的詳細
- **マーカー検出記録フロー**:
  1. markerFoundイベント → recordMarkerDetection呼び出し
  2. LocalStorageで当日のキャッシュ確認 → あればスキップ
  3. recordMarkerScan(markerId, markerName, 'marker_scan')を呼び出し
  4. MarkerScanController::record()で日付ベースの重複チェック
  5. 重複がなければDBに記録、LocalStorageにキャッシュ

- **ボールヒット記録フロー**:
  1. collectStamp関数 → recordMarkerScan(stampId, name, 'ball_hit')呼び出し
  2. MarkerScanController::record()で記録（重複チェックなし）
  3. タイプ別にscan_countを集計

- **データベーススキーマ変更**:
  - capture_typeカラム: VARCHAR(20), default 'ball_hit'
  - インデックス: capture_type, (marker_id, capture_type), (fingerprint, marker_id, capture_type)

#### 動作確認項目
- ✅ マイグレーション実行成功（capture_typeカラム追加）
- ✅ PHP構文エラーなし（全ファイル）
- **テスト項目（実装後に確認が必要）**:
  - [ ] マーカー検出時にmarker_scansに記録される（capture_type: 'marker_scan'）
  - [ ] ボールヒット時にmarker_scansに記録される（capture_type: 'ball_hit'）
  - [ ] 同じ日に同じマーカーを再検出しても、カウントアップされない
  - [ ] 捕獲済みのマーカーは記録されない
  - [ ] ダッシュボードで各動物のマーカー検出回数とボールヒット回数が表示される
  - [ ] 積み上げ棒グラフがタイプ別に表示される
  - [ ] 既存機能（スタンプ収集、スタンプ帳表示）への影響なし

#### 設計ドキュメント
- 要件定義6: `.claude_workflow/requirements.md` (要件定義6セクション)
- 設計6: `.claude_workflow/design.md` (設計6セクション)
- タスク化6: `.claude_workflow/tasks.md` (タスク化6セクション)

---

### ARスタンプラリー202603 - スタンプ帳アイコン表示改善（追加修正） 20260219
**未収集動物のアイコン表示を統一:**

#### 実装内容
- 未収集の通常動物15種のアイコンを絵文字から足跡（🐾）に変更
- すべての未収集動物（パンダ、シークレット、通常動物）が足跡で統一表示される
- ユーザーが「まだ見つけていない動物」であることをより明確に認識できる

#### 変更ファイル
- `resources/views/ARstampRally202603.blade.php`: showStampBook関数（3390行目に1行追加）

#### 表示結果
**未収集時**:
- パンダ: 🐾（足跡） + 「パンダ」
- シークレット（パンダ以外）: 🐾（足跡） + 「シークレット」
- 通常動物15種: 🐾（足跡） + 「？？？」（**絵文字から足跡に変更**）

**収集済み時**:
- 全動物: スクリーンショットまたは絵文字 + 実際の名前（変更なし）

#### 動作確認済み項目
- ✅ 未収集動物のアイコンが🐾で統一表示される
- ✅ 収集済み動物の表示は変更なし
- ✅ CSS効果（grayscale、text-shadow）が正しく適用される
- ✅ 既存機能への影響なし

#### 設計ドキュメント
- 要件定義5: `.claude_workflow/requirements.md` (要件定義5セクション)
- 設計5: `.claude_workflow/design.md` (設計5セクション)
- タスク化5: `.claude_workflow/tasks.md` (タスク化5セクション)

---

### ARスタンプラリー202603 - スタンプ帳UI改善と管理画面統計追加 20260219
**ARstampRally202603.blade.phpのユーザー体験向上と管理者向け統計機能の追加:**

#### 実装内容
1. **スタンプ帳UI改善**
   - ヒントボタンを非表示化（1931行目をコメントアウト）
   - 未収集動物の名前表示ロジックを変更：
     - **パンダ**: 未収集でも「パンダ」と表示（シークレットだが名前を明示）
     - **シークレット（パンダ以外）**: 未収集時は「シークレット」と表示
     - **通常動物15種**: 未収集時は「？？？」と表示（興味喚起）
   - 収集済み動物は実際の名前が表示される（変更なし）

2. **管理画面統計ページ追加**
   - URL: `/admin/dashboard202603`
   - パンダマーカー専用の統計ダッシュボードを新規作成
   - 表示内容：
     - 総パンダスキャン数
     - ユニークユーザー数
     - 最近のパンダスキャン履歴（ページネーション付き）
     - 日別パンダスキャン数（テーブル + グラフ表示）
   - 既存ダッシュボードと統一感のあるデザイン
   - 認証必須（admin.authミドルウェア）
   - 双方向ナビゲーションリンク

3. **変更・追加ファイル**
   - `resources/views/ARstampRally202603.blade.php`: スタンプ帳UI改善（3箇所修正）
   - `app/Http/Controllers/AdminController.php`: dashboard202603()メソッド追加
   - `routes/web.php`: 新規ルート追加
   - `resources/views/admin/dashboard202603.blade.php`: 新規ビュー作成
   - `resources/views/admin/dashboard.blade.php`: ナビゲーションリンク追加

4. **技術的詳細**
   - MarkerScanモデルを使用してパンダマーカーの統計を取得
   - `where('marker_id', 'panda')` でフィルタリング
   - ページネーション対応（スキャン履歴30件/ページ、日別15件/ページ）
   - グラフはCSS barチャートで実装（軽量化）
   - 30秒ごとに自動更新

#### 動作確認済み項目
- ✅ ヒントボタンが非表示
- ✅ 未収集動物名の表示が要件通り（パンダ/シークレット/通常）
- ✅ 管理画面へのアクセスが認証で保護されている
- ✅ パンダマーカーの統計が正しく表示される
- ✅ ページネーションが機能する
- ✅ ナビゲーションが双方向で機能する
- ✅ 既存機能への影響なし

#### 設計ドキュメント
- 要件定義: `.claude_workflow/requirements.md` (要件定義4セクション)
- 設計: `.claude_workflow/design.md` (設計4セクション)
- タスク化: `.claude_workflow/tasks.md` (タスク化4セクション)

---

### ARスタンプラリー202603版 - LocalStorage分離実装 20260217
**ARstampRally.blade.phpをコピーして作成したARstampRally202603.blade.phpにおいて、LocalStorageキーの重複問題を解決:**

#### 実装内容
1. **問題の特定と解決**
   - `/stamp` と `/stamp202603` が同じLocalStorageキーを使用していたため、データが混在
   - 全てのストレージキーに `-202603` サフィックスを追加することで完全にデータを分離

2. **変更箇所（14箇所）**
   - **IndexedDB名**: `'ARStampRallyDB'` → `'ARStampRallyDB202603'`
   - **LocalStorageキー**: 
     - `'ar-user-id'` → `'ar-user-id-202603'`
     - `'ar-stamp-rally'` → `'ar-stamp-rally-202603'` (5箇所)
     - `'ar-captured-animals'` → `'ar-captured-animals-202603'` (4箇所)
     - `'ar-prize-exchanged'` → `'ar-prize-exchanged-202603'`
     - `'ar-prize-code'` → `'ar-prize-code-202603'`
   - **Cookie名**: `'ar_user_id'` → `'ar_user_id_202603'`

3. **技術的詳細**
   - ファイル: `resources/views/ARstampRally202603.blade.php` (6908行)
   - 変更対象: 変数定義3箇所 + localStorage文字列リテラル11箇所
   - 元ファイル (`ARstampRally.blade.php`) は一切変更なし
   - PHP構文エラーなし（`php -l` で検証済み）

4. **データ分離の効果**
   - `/stamp` と `/stamp202603` で完全に独立したスタンプラリー進行状況を保持
   - それぞれのページで捕獲した動物が他方に表示されない
   - 景品交換機能も独立して動作
   - ユーザーIDもページごとに別管理

#### 検証方法（Task 5）
1. ブラウザ開発者ツール（F12）→ Application タブ
2. LocalStorage、Cookie、IndexedDB を確認
3. `/stamp202603` で動物を捕獲 → `-202603` サフィックス付きキーが作成される
4. `/stamp` にアクセス → スタンプ帳が空（データ混在なし）

#### 設計ドキュメント
- 要件定義: `.claude_workflow/requirements.md` (ARスタンプラリー202603版セクション)
- 設計: `.claude_workflow/design.md` (ARスタンプラリー202603版セクション)
- タスク化: `.claude_workflow/tasks.md` (ARスタンプラリー202603版セクション)

---

### WebVR中心暗転体験アプリ - 動的版実装 20260203
**A-Frameを使用したVR中心暗転（逆トンネルビジョン）シミュレーション - 時間経過で変化:**

#### 実装内容
1. **360度パノラマVR環境**
   - `R0010034.JPG`を使用した没入型360度画像表示
   - Pico4 Enterprise対応のWebXRアプリケーション
   - アクセスURL: `/vr-center-dark`

2. **動的中心暗転エフェクト（時間変化）**
   - **VRモード開始時に自動的にタイマースタート**
   - **50秒で1ループ**、連続再生
   - **滑らかな線形補間**で段階間を遷移
   - **5つのステージ**で暗転範囲が変化:
     - 0～10秒: 中心暗転なし
     - 10～20秒: 視野角20度程度（小さい円）
     - 20～30秒: 視野角30度程度（中くらいの円）
     - 30～40秒: 視野角40度程度（やや大きい円）
     - 40～50秒: 視野角50度以上（大きい円）
   - リアルタイムで視線に追従するエフェクト
   - カスタムGLSLシェーダーによる高品質なグラデーション
   - **視野狭窄アプリの逆エフェクト**: シェーダーの`alpha`を反転（`1.0 - alpha`）するだけで実現

3. **技術実装**
   - **フレームワーク**: A-Frame 1.4.0（WebXR対応）
   - **カスタムコンポーネント**: `center-dark-overlay`
   - **シェーダー**: 
     - Vertex Shader: 頂点位置の計算（視野狭窄と同じ）
     - Fragment Shader: 視線中心からの角度に基づく透明度制御（**反転ロジック追加**）
   - **動的制御**:
     - `tick()`メソッド: 毎フレーム経過時間とステージを計算
     - `lerp()`関数: ステージ間の線形補間
     - VRモード検知: `enter-vr`イベントリスナー
     - 50秒ループ: モジュロ演算（`% 50`）
   - **最適化**: 
     - 球体セグメント数48（パフォーマンスと品質のバランス）
     - 球体半径0.4m（最適な視覚効果）
     - 深度テストオフ（常に最前面表示）

#### ファイル構成
- `routes/web.php`: `/vr-center-dark`ルート追加
- `resources/views/vr-center-dark.blade.php`: メインVRシーン
- `public/js/vr-center-dark/center-dark.js`: カスタムコンポーネント＋シェーダー（反転＋動的ロジック）
- `public/cg/R0010034.JPG`: 360度パノラマ画像（視野狭窄と共通）

#### パラメータ（動的変化）
```javascript
// ステージ定義
stages: [
  { time: 0,  innerRadius: 0.00, outerRadius: 0.00 },  // 暗転なし
  { time: 10, innerRadius: 0.00, outerRadius: 0.00 },  // ステージ境界
  { time: 20, innerRadius: 0.11, outerRadius: 0.15 },  // 視野角20度
  { time: 30, innerRadius: 0.17, outerRadius: 0.23 },  // 視野角30度
  { time: 40, innerRadius: 0.22, outerRadius: 0.30 },  // 視野角40度
  { time: 50, innerRadius: 0.28, outerRadius: 0.36 }   // 視野角50度
];
loopDuration: 50秒  // 50秒で最初に戻る
```

#### シェーダーの核心：反転ロジック + 動的更新
```glsl
// Fragment Shader（視野狭窄からの反転）
float alpha = smoothstep(innerRadius, outerRadius, normalizedDistance);
alpha = 1.0 - alpha;  // ← この1行で反転（中心=黒、外側=透明）
```

```javascript
// 動的パラメータ更新（tick()メソッド内）
const elapsedTime = (Date.now() - this.startTime) / 1000;
const loopTime = elapsedTime % 50;  // 50秒ループ
// ステージ間で線形補間
const innerRadius = this.lerp(currentStage.innerRadius, nextStage.innerRadius, stageProgress);
this.material.uniforms.innerRadius.value = innerRadius;
```

#### 使用方法
1. **デスクトップ**: `http://localhost:8000/vr-center-dark`にアクセス（VRモード前は暗転なし）
2. **VRデバイス**: 同URLにアクセスし、VRボタンでVRモード起動
3. **VRモード開始と同時にタイマースタート**、10秒後から暗転開始
4. Pico4 Enterpriseで頭を動かして時間変化する中心暗転体験
5. **50秒経過後、自動的にループして最初から再生**

#### 動作の詳細

**タイムライン（50秒周期）:**
```
0:00～0:10  │████████████████████│ 中心暗転なし（完全に明瞭）
0:10～0:20  │      ●●●●●●        │ 視野角20°の小さい円が暗転
0:20～0:30  │    ●●●●●●●●●●      │ 視野角30°の中くらいの円に拡大
0:30～0:40  │  ●●●●●●●●●●●●●●    │ 視野角40°のやや大きい円に拡大
0:40～0:50  │●●●●●●●●●●●●●●●●●●  │ 視野角50°の大きい円に拡大
0:50～      │████████████████████│ 最初に戻る（暗転なし）
```

**遷移の仕組み:**
- 各ステージ間は**線形補間（lerp）**で滑らかに遷移
- 例: 15秒時点（10～20秒の中間）では、視野角10°相当の暗転
- フレームレート: 60fps～90fps（デバイス依存）
- 補間計算: 毎フレーム実行（リアルタイム更新）

**VRモード検知:**
- A-Frameの`enter-vr`イベントを使用
- デスクトップの疑似VRモードでも動作
- タイマーはVRモード中のみカウント（exit-vrで一時停止しない仕様）

**技術的な特徴:**
1. **視線追従**: カメラの向きに関係なく、常に視線中心が暗転
2. **パフォーマンス**: シェーダーベースで軽量（GPUで処理）
3. **互換性**: WebXR対応ブラウザ全般で動作（Chrome, Firefox, Oculus Browser等）
4. **レスポンシブ**: 画面サイズや解像度に自動対応

#### トラブルシューティング

**Q: VRモードに入っても暗転が始まらない**
- A: 10秒待ってください。最初の10秒は暗転なしの期間です。

**Q: 暗転の円がカクカク動く**
- A: デバイスの性能不足の可能性。球体セグメント数を減らすと改善します：
  ```javascript
  // center-dark.js内で変更
  segments: { type: 'int', default: 32 }  // 48から32に減らす
  ```

**Q: ブラウザのコンソールにエラーが表示される**
- A: 以下を確認してください：
  - A-Frameのバージョン（1.4.0推奨）
  - Three.jsが正しく読み込まれているか
  - WebXR APIがサポートされているか（chrome://flags/で確認）

**Q: デスクトップで動作確認できない**
- A: VRボタンをクリックすると疑似VRモードになります。Escキーで解除。

**Q: タイマーをリセットしたい**
- A: VRモードを一旦終了（Escキー）し、再度VRボタンをクリック。

#### カスタマイズ方法

**ステージ時間を変更する:**
```javascript
// center-dark.js内のstages配列を編集
this.stages = [
  { time: 0,  innerRadius: 0.00, outerRadius: 0.00 },
  { time: 5,  innerRadius: 0.00, outerRadius: 0.00 },  // 5秒に短縮
  { time: 10, innerRadius: 0.11, outerRadius: 0.15 },  // 10秒に短縮
  // ...以降も調整
];

// tick()メソッド内のループ時間も変更
const loopTime = elapsedTime % 30;  // 50秒→30秒に短縮
```

**暗転の強さを変更する:**
```javascript
// schema内で不透明度を変更
opacity: { type: 'number', default: 0.8 }  // 1.0→0.8で薄く
```

**暗転範囲を変更する:**
```javascript
// stages配列のinnerRadius/outerRadiusを調整
{ time: 20, innerRadius: 0.15, outerRadius: 0.20 }  // より大きい円
```

#### 設計ドキュメント
- `.claude_workflow/requirements_center_dark.md`: 要件定義（動的版）
- `.claude_workflow/design_center_dark.md`: 設計書（動的版）
- `.claude_workflow/tasks_center_dark.md`: タスク一覧（動的版）

#### 既存アプリとの比較
| 項目 | 視野狭窄アプリ | 中心暗転アプリ（動的版） |
|------|---------------|---------------|
| URL | `/vr-tunnel` | `/vr-center-dark` |
| エフェクト | 中心=明瞭、周辺=暗転 | 中心=暗転、周辺=明瞭 |
| パラメータ | 固定値 | **時間経過で動的に変化（5ステージ、50秒ループ）** |
| タイマー | なし | **VRモード開始時に自動スタート** |
| 用途 | トンネルビジョン体験 | 時間変化する中心暗点体験 |
| 実装差異 | - | シェーダー反転＋tick()メソッド＋VRイベントリスナー |

---

### WebVR視野狭窄体験アプリ - 新規実装 20260203
**A-Frameを使用したVR視野狭窄（トンネルビジョン）シミュレーション:**

#### 実装内容
1. **360度パノラマVR環境**
   - `R0010034.JPG`を使用した没入型360度画像表示
   - Pico4 Enterprise対応のWebXRアプリケーション
   - アクセスURL: `/vr-tunnel`

2. **視野狭窄エフェクト（トンネルビジョン）**
   - 視線中心部（視野角約30度）のみ明瞭に表示
   - 周辺部は滑らかに暗転（視野角50度以降で完全な黒）
   - リアルタイムで視線に追従するエフェクト
   - カスタムGLSLシェーダーによる高品質なグラデーション

3. **技術実装**
   - **フレームワーク**: A-Frame 1.4.0（WebXR対応）
   - **カスタムコンポーネント**: `tunnel-vision-overlay`
   - **シェーダー**: 
     - Vertex Shader: 頂点位置の計算
     - Fragment Shader: 視線中心からの角度に基づく透明度制御
   - **最適化**: 
     - 球体セグメント数48（パフォーマンスと品質のバランス）
     - 球体半径0.4m（最適な視覚効果）
     - 深度テストオフ（常に最前面表示）

#### ファイル構成
- `routes/web.php`: `/vr-tunnel`ルート追加
- `resources/views/vr-tunnel.blade.php`: メインVRシーン
- `public/js/vr-tunnel/tunnel-vision.js`: カスタムコンポーネント＋シェーダー
- `public/cg/R0010034.JPG`: 360度パノラマ画像

#### パラメータ（固定値）
```javascript
innerRadius: 0.15    // 完全に透明な中心領域（視野角約30度）
outerRadius: 0.35    // 完全に黒くなる外側（視野角約50度）
sphereRadius: 0.4    // 球体の半径（メートル）
opacity: 0.95        // 暗転部の不透明度
segments: 48         // 球体セグメント数
```

#### 使用方法
1. **デスクトップ**: `http://localhost:8000/vr-tunnel`にアクセス、マウスドラッグで視点変更
2. **VRデバイス**: 同URLにアクセスし、VRボタンでVRモード起動
3. Pico4 Enterpriseで頭を動かして視野狭窄体験

#### 技術的な特徴
- **固定パラメータ**: 常に一定の視野狭窄（視野角30～50度）
- **軽量**: シェーダーのみで実装、tick()メソッド不要
- **シンプル**: イベントリスナーなし、初期化のみで動作
- **高品質**: smoothstep関数による滑らかなグラデーション

#### 中心暗転アプリとの違い
| 機能 | 視野狭窄 | 中心暗転（動的版） |
|------|----------|-------------------|
| エフェクト | 中心=明瞭、周辺=暗転 | 中心=暗転、周辺=明瞭 |
| 時間変化 | なし（固定） | あり（5ステージ、50秒ループ） |
| 実装複雑度 | シンプル | 中程度（タイマー＋補間） |
| 用途 | トンネルビジョン体験 | 中心暗点の段階的変化 |
| コード量 | 約160行 | 約200行 |

#### 設計ドキュメント
- `.claude_workflow/requirements.md`: 要件定義
- `.claude_workflow/design.md`: 設計書
- `.claude_workflow/tasks.md`: タスク一覧

---

### VRシューティングゲーム - バグ修正とUI改善 20260121
**shooting3Danimal.blade.php の修正履歴:**

#### 修正内容
1. **ゲームスタートボタン クリック不具合修正**
   - 問題: Level 1 / Level 2 ボタンがクリックできない
   - 原因: setupListeners が DOM 読み込み前に呼ばれていた
   - 解決: 複数のタイミング戦略と重複防止フラグを実装

2. **コンソールデバッグ有効化**
   - 問題: console.clear() が1秒ごとに実行され、デバッグ情報が消える
   - 解決: 開発者ツール検出コード（175-193行目）をコメントアウト

3. **JavaScript構文エラー修正**
   - 問題: 2115行目で構文エラー（if文の不適切な構造）
   - 解決: if (!window.gameStarted || !this.isMoving) のブロック構造を修正

4. **3Dモデル配置・サイズ調整**
   - 問題: モデルがカメラ位置に出現、サイズが25-26倍に拡大
   - 原因: approach-camera が動的カメラ位置を使用、fit-to-hitbox の計算ミス
   - 解決1: カメラ WASD 移動を無効化（3290行目）
   - 解決2: 全モデルを遠方位置 (0, 0, -50) に初期配置（3186-3265行目）
   - 解決3: fit-to-hitbox を固定スケール 0.5 に変更（300-330行目）
   - 解決4: スポーン距離を 10-12m → 4-5m に短縮（740-756行目）

5. **投げるボールの視認性改善**
   - 問題: ボールが小さすぎて見えない、暗い
   - 解決1: スケールを 0.1 → 0.5 に拡大（その後ユーザーが 0.2 に調整）
   - 問題2: ボールが白く見える（リンゴなのに赤色が失われる）
   - 原因: 発光（emissive）による色の上書き
   - 解決2: 発光を完全に削除し、元のモデル色を保持（1873-1886行目）
   
#### 技術的な変更詳細
- **Line 175-193**: Developer tools detection → コメントアウト
- **Line 300-330**: fit-to-hitbox component → 固定 scale: 0.5
- **Line 438-531**: start-menu setupListeners → タイミング改善
- **Line 740-756**: Movement patterns → 距離 4-5m に調整
- **Line 1869**: Ball scale → 0.5（ユーザーが後に 0.2 に変更）
- **Line 1873-1886**: Ball material → 発光なし、元の色のみ使用
- **Line 2100-2130**: approach-camera tick → 構文エラー修正、target position を原点に固定
- **Line 3186-3265**: modelGroup entities → position "0 0 -50", active: false
- **Line 3290**: Camera → wasd-controls disabled

---

### VRシューティングゲーム - 2種類のボール切り替え機能実装 20260120
**shooting3Danimal.blade.php の新機能:**

#### 実装内容
1. **2種類のポケボール対応**
   - `poke_ball_07apple.glb` (リンゴ型ポケボール)
   - `poke_ball_09cabbage.glb` (キャベツ型ポケボール)
   - トリガーを引いて投げるボールが2種類から選択可能

2. **VRコントローラーのグリップボタンでボール切り替え**
   - 左右どちらのコントローラーでもグリップボタンでボールタイプを切り替え可能
   - ゲーム中のみ有効（ゲーム開始前・終了後は無効）

3. **PC用キーボード操作の追加**
   - **Spaceキー**: ボールを投げる（トリガーボタン代替）
   - **Gキー**: ボールを切り替える（グリップボタン代替）
   - **マウスクリック**: ボールを投げる

4. **コントローラー先端にボールプレビュー表示**
   - 現在選択されているボールがVRコントローラーの先端に小さく表示
   - 回転アニメーション付き
   - ボール切り替え時に即座に更新

5. **UI改善**
   - スタートメニューにPC操作説明を追加（緑色テキスト）
   - ゲーム開始時にコンソールへPC操作ガイドを表示

#### 技術実装詳細
- **グローバル変数追加:**
  ```javascript
  window.ballTypes = ['cg/poke_ball_07apple.glb', 'cg/poke_ball_09cabbage.glb'];
  window.currentBallIndex = 0; // 現在選択中のボール
  ```

- **vr-controllerコンポーネント拡張:**
  - `onGripDown()`: グリップボタンイベントハンドラ
  - `createBallPreview()`: コントローラー先端にプレビュー作成
  - `updatePreview()`: ボール切り替え時にプレビュー更新

- **ball-shooter (shoot)コンポーネント修正:**
  - `onKeyDown()`にGキーの処理を追加
  - `shoot()`で`window.ballTypes[window.currentBallIndex]`を使用

#### ファイル構成
- 元ファイル: `shooting3Dcute.blade.php`をコピー
- 新ファイル: `shooting3Danimal.blade.php`
- 使用モデル: 同じ動物モデル（whiteTiger, pengin, namakemono等）
- 使用ボール: 新規に2種類のポケボール

### ダッシュボードについて　20251121
    /admin/login - ログインページ
    /admin/dashboard - ダッシュボード（認証必要）
    /admin/logout - ログアウト




## スタンプラリーアイデア
　・スタンプ帳にあらかじめ、シルエット的なものが表示され、スタンプを押すと、表示
　・スタンプ帳にシークレット
　・スタンプがたまると景品交換ボタン（ただし、端末ごとに1度しか交換できない仕様にする。DBで管理？？）
　・スタンプを押すボタン　➡　　GETするボタンに変更
　　　※乱数を使って、GET失敗するパターンも欲しい。
　　　　モンスターボールを投げる？？（当たり判定ボックス）


## terrer アイデア
　・BOSSの出現時に、ブラックアウト＆音楽変更＆ステージ変更（背景）
　・ボールに球数制限
　・武器変更
　・ミッションを課して、成功するとスコアアップ
　・体力ゲージ
　・武器を手に入れることができる味方（or 宝箱）を表示
　・BOSS撃退後は、トビラが表示され、OPEN　次のステージへ

## Cute アイデア
　・ボールではなく、Pikuminを投げる（Animetionついたまま）
　　ｙ座標が０のところで着地して、投げた方向に走っていく。当たり判定ぶつかるとAnime02に切替

## VR cute と vr Terrer を完全オフライン仕様に変更しようとしたが、どうしてもフォントが表示されない…ので巻き戻します　2025.11.18



## git コマンド
git log --oneline -10
git reset --hard ******
##### リモートリポジトリを強制的に特定のコミットに戻すには、force pushが必要です。
git push origin main --force



##AIモデル評価点ランキング（総合評価 vs. 実践評価）
## 管理ダッシュボード - CSVエクスポート機能 (追加)
管理ダッシュボードから以下のデータを CSV でダウンロード可能になりました（管理者ログイン後）:

- 未使用の景品交換 (recentExchanges)
- 使用済み景品交換 (redeemedPrizes)
- 全ての景品交換 (allExchanges)
- 最近のスキャン履歴 (recentScans)
- 日別スキャン数の集計 (dailyScans)
- マーカー別スキャン統計 (markerStats)

ダッシュボードの CSV エクスポート欄で、データセットを選択し、開始/終了日時を入力して「CSV をダウンロード」を押すと、フィルタ条件で絞った CSV がダウンロードされます。

注意: start/end の値は `datetime-local` 形式で入力してください（例: 2025-11-23T13:00）。未指定だと全期間のデータが出力されます。

順位	アクセス権限テストリスト (AIモデル)	評価点 (総合)	実践評価点	倍率	主な評価理由 (実践的)
1	12-GPT-5.1	5.0	5.0	x1	優れた網羅性と粒度。 否定優先、論理ロール制約、DL-08必須項目の保存検証が詳細かつ、実装フェーズに即した順序。
2	5-Raptor mini	5.0	4.9	x0	構造化と優先度付けが即戦力。 9カテゴリ分類、優先度（高/中）が明確。競合トランザクション、セキュリティ侵害ケースなど現実の課題を考慮。
3	10-GPT-5	4.8	5.0	x1	ユニットテストの粒度が最も TDD に適している。 複雑なロジック（否定優先、キャッシュ整合性）をデバッグしやすいよう細分化。
4	8-Claude Sonnet 4.5	4.9	4.8	x1	フェーズ構成とチェックリストが実装ガイドになる。Layer 1〜3の検証構造が明確で、DL-08の完全追跡要件への言及も詳細。
5	13-GPT-5.1-Codex	4.7	4.7	x1	IDと監査項目が堅牢。改ざん防止ハッシュのチェーン整合性など、高度なセキュリティ要件に具体的に踏み込む。
6	11-GPT-5-Codex	4.7	4.7	x1	三層認可の検証順序が論理的。「Layer 1/2 をバイパスしても RLS が拒否する」テストなど、最終ガード機能の検証が実践的。
7	7-Claude Sonnet 4	4.5	4.4	x1	堅牢な構造。Layer 1〜3の階層別テスト戦略と、DL-08の全項目追跡、NFR-01性能要件のチェックリストが詳細に整理。
8	6-Claude Haiku 4.5	4.5	4.4	x0.33	構造と期間推定が実践的。実装者が作業完了を判断する基準となる「実装指標」と、プロジェクト計画に役立つ期間推定を提供。
9	1-GPT-4.1	4.3	4.0	x0	網羅性は高いが粒度が粗い。TDDの「最小のテスト」としてはやや粒度が大きく、デバッグが難しくなる可能性がある。
10	4-Grok Code Fast 1	4.2	4.1	x0	Given-When-Then 形式の徹底。実装時にテストコードに変換しやすいが、ユニットレベルでの分割は上位モデルに劣る。
11	3-GPT-5 mini	4.0	3.9	x0	分類は良いが具体性が不足。DL-08の詳細監査項目など、設計書で強調されている複雑な要件への言及が少ない。
12	14-GPT-5.1-Codex-Mini	3.8	3.5	x0.33	要点に絞りすぎ。TDDに必要な網羅的なユニットテストの分解が不足しており、実装ドライバーとしては不十分。

---

### VRシューティングゲーム - Pico4パフォーマンス改善（traverse+dispose廃止） 20260319

**対象ファイル:** `shooting3Dterrer3.blade.php` / `shooting3DModel3.blade.php` / `shooting3Danimal3.blade.php`

Pico4 Enterprise（Snapdragon XR2）でVRブラウザがフリーズ・強制終了する問題を修正。
THREE.jsの不適切なリソース解放処理が原因。

#### 修正内容（3ファイル共通）

**Fix 1: aframe-physics-system 削除**
- `<script src="{{ asset('js/aframe-physics-system.min.js') }}"></script>` を削除
- `<a-scene>` から `physics="gravity: -9.8"` 属性を削除
- 物理演算エンジン（Cannon.js）を未使用なのにロードしていた

**Fix 2: restartGame のシーン全体dispose廃止**
- `model.object3D.traverse()` で geometry/material/texture を dispose するブロックを削除
- モデル削除は `removeChild` のみに変更
- シーン全体を traverse することでライト・スカイ・UIのリソースまで破壊していた

**Fix 3: anisotropy を 16 → 2 に変更**
- `enhance-materials` コンポーネント内の4箇所を変更
  - `node.material.map.anisotropy`
  - `node.material.metalnessMap.anisotropy`
  - `node.material.roughnessMap.anisotropy`
  - `node.material.normalMap.anisotropy`
- Snapdragon XR2 の最大異方性フィルタリング値は約4。16を指定するとGPUドライバーのオーバーヘッドが発生

**Fix 4: ヒット・削除時の traverse+dispose 廃止**
- `ball.object3D.traverse()` dispose ブロックを以下の全箇所から削除
  - ボールがモデルにヒットした後のコールバック（300ms遅延）
  - ボールが地面に落下・タイムアウトした際の削除処理
  - `showResult`（ゲーム終了時の残存ボール一括削除）
  - `restartGame`（リスタート時の残存ボール一括削除）
- A-Frameは GLBジオメトリ/マテリアルをキャッシュ・共有しているため、dispose すると次の描画でGPUストールが発生していた
- ボール削除は `removeChild` のみに統一

#### 変更ファイルと行数変化
- `shooting3Dterrer3.blade.php`: 参照元（先行修正済み）
- `shooting3DModel3.blade.php`: 3406行 → 3274行（132行削減）
- `shooting3Danimal3.blade.php`: 3730行 → 3640行（90行削減）

#### 技術的背景
- **ハードウェア**: Pico4 Enterprise / Qualcomm Snapdragon XR2 / 8GB LPDDR5 / 256GB UFS 3.1
- **フレームワーク**: A-Frame VR + THREE.js（Bladeテンプレート）
- THREE.jsはGLBをロードするとリソースをキャッシュ・共有する。そのリソースを `dispose()` で破棄すると、同じリソースを参照している別オブジェクトの描画時にGPUへの再アップロードが発生し、フリーズの原因となる
- renderer.renderLists.dispose() / renderer.info.reset() / THREE.Cache.clear() は残置（シーン全体のフレームバッファリセットは安全）
13	2-GPT-4o	2.5	2.0	x0	実践的な利用は困難。テスト内容が抽象的で、具体的な入力条件やDL-08の個別監査項目の検証がほとんど含まれていない。


#### ishimaru
