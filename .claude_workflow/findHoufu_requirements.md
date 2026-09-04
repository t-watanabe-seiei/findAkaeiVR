# findHoufu 要件定義

> 作成日: 2026-07-09  
> 最終更新: 2026-09-03  
> 状態: 修正対応中

---

## 0. 修正追加要件（2026-09-03）

shooting3Dhalloween4 との差異調査により、以下の不備を特定し修正が必要。

### 0.1 VR 自動入場（VRゴーグル）

| # | 要件 | 現状 | 目標 |
|---|------|------|------|
| RV1 | VRゴーグルでページを開いた際に、シーン読み込み完了後 1 秒で自動 VR 入場 | `auto-enter-vr` が `loaded` を待たず即座に `enterVR()` → 失敗 | `loaded` イベントを待ってから WebXR 確認 → `enterVR()`（shooting3Dhalloween4 と同等） |

### 0.2 VR ボタン（A-Frame 標準）

| # | 要件 | 現状 | 目標 |
|---|------|------|------|
| RV2 | `body > a-scene > div.a-enter-vr > button` 構造で標準 VR 入場ボタン表示 | `vr-mode-ui="enabled: true"` ありだが `vr-controller` 未登録でシーン初期化に異常あり | `vr-controller` を登録し、A-Frame 標準 VR ボタンが正常に表示される |

### 0.3 PC マウス操作

| # | 要件 | 現状 | 目標 |
|---|------|------|------|
| RP1 | PC で START ボタンをマウスクリックで押せる | `start-menu` の `clickBlocked` が `true` 初期化され `false` に戻らない → 永久にブロック | `clickBlocked` を `false` で初期化（shooting3Dhalloween4 と同等） |
| RP2 | PC でゲーム開始後、マウスクリックでボール射撃 | `shoot` の `triggerdown` リスナーがカメラに付いている（PC では発火しない） | PC: `document mousedown` / VR: コントローラー `triggerdown` にリスナー（shooting3Dhalloween4 と同等） |

### 0.4 VR コントローラー射撃

| # | 要件 | 現状 | 目標 |
|---|------|------|------|
| RV3 | VR で左右コントローラー Trigger 引いたらボールが飛ぶ | `triggerdown` リスナーがカメラ实体に付いている → コントローラーから発火しない | `leftController` / `rightController` 实体に `triggerdown` リスナーを付与 |

---

## 1. 目的

防府市の観光名所（360° 画像）を VR で体験しながら、観光振興キャラクター **Bucchi** にボールを当ててスコアを競う VR ゲーム「findHoufu」を実装する。

## 2. 現状把握

| 参照先 | 状況 |
|--------|------|
| `findakaei.blade.php` | 単一ファイル 412 行。`OnStartButtonClick()` 未定義、CSRF なし、axios 未使用、タイマー精度不良 |
| `shooting3Dhalloween4/` | 3 ファイル分割、オブジェクトプーリング、GPU リソース管理、CSRF 対応。構成・設計の参照モデル |

## 3. 成功基準

- [ ] 3 ファイル分割（`index` / `_components` / `_scene`）
- [ ] VR 自動入場 + gun_01.glb コントローラー
- [ ] 7 ステージ構成（Stage1〜6: プレイ, Stage7: リザルト）
- [ ] ボール射撃 → モデルヒット → アニメ切替 → フェードアウト → 再出現
- [ ] タイムベーススコア + コンボ倍率
- [ ] BGM / 効果音（出現音・ヒット音）
- [ ] スコア POST + TOP5 ランキング表示
- [ ] パフォーマンス対策（プーリング・dispose・スロットリング）
- [ ] 英語メッセージ（開始画面・終了画面）

---

## 4. 機能要件

### 4.1 基本機能

| # | 機能 | 説明 |
|---|------|------|
| F1 | VR 自動入場 | `auto-enter-vr` コンポーネント。WebXR 対応ブラウザで 1 秒後に自動入場 |
| F2 | スタートメニュー | タイトル「seieiVR FIND BUCCHI」、英語メッセージ、START ボタン |
| F3 | ボール射撃 | VR: 右コントローラー triggerdown / PC: マウスクリック。最大同時 2 発 |
| F4 | モデル出現 | 位置 1〜6 からランダム選択。`anime01` ループ再生 + 出現音 |
| F5 | ヒット判定 | ボールがモデルの当たり判定（cylinder）に接触 |
| F6 | ヒット演出 | `anime02` 1 回再生 → 0.5 秒後にフェードアウト + ヒット音 |
| F7 | 再出現 | フェードアウト完了後 0.5 秒で、別位置に再出現 |
| F8 | ステージ進行 | タイマー終了 → 暗転フェード → 次ステージ（背景・BGM 切り替え） |
| F9 | スコア表示 | HUD に現在スコア・コンボ・残り時間表示 |
| F10 | リザルト | Stage7: モデル出現 + スコア POST + TOP5 表示 + Particle + 12 秒後フェードアウト → VR 退出 → reload |

### 4.2 スコアリング

| 項目 | 仕様 |
|------|------|
| 基本点 | `max(5, round(50 × (1 - hitTime / stageTimeLimit)))` |
| コンボ倍率 | `1 + min(comboCount, 10) × 0.1`（最大 2.0 倍） |
| 最終点 | `round(baseScore × comboMultiplier, 1)` |
| コンボ増加 | ヒット毎に +1 |
| コンボリセット | ボールがヒットしなかった場合（ミス） |
| 記録 | 総スコア、最大コンボ、ヒット回数 |

**hitTime 例（stageTimeLimit=12s）:**

| hitTime | 基本点 |
|---------|--------|
| 0.5s | 48 |
| 2s | 42 |
| 5s | 31 |
| 8s | 17 |
| 11s | 4 → **5**（下限） |

### 4.3 ステージ構成

| Stage | 時間 | 背景 | BGM | 備考 |
|-------|------|------|-----|------|
| 1 | 10s | sky01 | BGM S1 (`sound_bgm11.mp3`) | |
| 2 | 12s | sky02 | BGM S1 | |
| 3 | 12s | sky03 | BGM S2 (`sound_bgm12.mp3`) | |
| 4 | 12s | sky04 | BGM S2 | |
| 5 | 12s | sky05 | BGM S3 (`sound_bgm13.mp3`) | |
| 6 | 12s | sky06 | BGM S3 | |
| 7 | 12s | sky01 | BGM S4 (`sound_bgm14.mp3`) | リザルト |

### 4.4 6 位置（findakaei より流用）

| 位置 | position | rotation | scale |
|------|----------|----------|-------|
| 1 | `-2 -0.6 1` | `0 120 0` | `1.4 1.4 1.4` |
| 2 | `10 -0.88 -1.9` | `0 -90 0` | `2.7 2.7 2.7` |
| 3 | `-1.325 1.0 4.00` | `0 150 0` | `1.4 1.4 1.4` |
| 4 | `6.0 0 0.13` | `0 -120 0` | `2.1 2.1 2.1` |
| 5 | `-0.5 0 -0.5` | `0 0 0` | `1 1 1` |
| 6 | `-4.5 0.9 4.6` | `0 130 0` | `1.9 1.9 1.9` |

### 4.5 メッセージ（英語）

| 場面 | メッセージ |
|------|-----------|
| スタート画面 | `Explore the tourist spots of Houfu City!` |
| 終了画面 | `Please visit the scenic spots of Houfu City! Bucchi might be hiding somewhere...` |

### 4.6 API

| 操作 | メソッド | エンドポイント | パayload |
|------|---------|---------------|----------|
| スコア送信 | POST | `api/findhoufu-scores` | `{name, score, max_combo, hits}` |
| ランキング | GET | `api/findhoufu-scores/top5` | — |

> CSRF: `<meta name="csrf-token">` + `X-CSRF-TOKEN` ヘッダ

---

## 5. 非機能要件

### 5.1 パフォーマンス

| # | 対策 | 説明 |
|---|------|------|
| P1 | オブジェクトプーリング | ボール最大 4 発プール |
| P2 | THREE.Color キャッシュ | emissive 色を再利用 |
| P3 | Vector3 再利用 | `_cachedPos` 等 |
| P4 | GPU リソース解放 | `disposeEntityResources()` |
| P5 | タイムアウト追跡 | `registerTimeout` + 一括クリア |
| P6 | モデル dispose & remove | フェードアウト後解放 |
| P7 | 左コントローラー laser 非表示 | `showLine: false` |
| P8 | tick スロットリング | 50〜100ms |
| P9 | 次ステージプリロード | 背景・BGM を背景ロード |

### 5.2 セキュリティ

| # | 対策 |
|---|------|
| S1 | CSRF トークン `<meta>` + fetch ヘッダ |
| S2 | `asset()` でローカル読み込み（CDN 非依存） |

---

## 6. アセット一覧

| 用途 | パス | 備考 |
|------|------|------|
| モデル | `asset('cg/202609/model01_bucchi.glb')` | anime01 / anime02 |
| 武器 | `asset('cg/gun_01.glb')` | コントローラー用 |
| ボール | `cg/poke_ball_05.glb` | 射撃用 |
| BGM S1 | `asset('cg/sound_bgm11.mp3')` | Stage 1-2 |
| BGM S2 | `asset('cg/sound_bgm12.mp3')` | Stage 3-4 |
| BGM S3 | `asset('cg/sound_bgm13.mp3')` | Stage 5-6 |
| BGM S4 | `asset('cg/sound_bgm14.mp3')` | Stage 7 |
| 出現音 | `asset('cg/sound_animal_appear.mp3')` | |
| ヒット音 | `asset('cg/sound_animal_die.mp3')` | |
| 背景 1 | `asset('cg/R0010095.JPG')` | sky01 |
| 背景 2 | `asset('cg/R0010109.JPG')` | sky02 |
| 背景 3 | `asset('cg/R0010111.JPG')` | sky03 |
| 背景 4 | `asset('cg/R0010114.JPG')` | sky04 |
| 背景 5 | `asset('cg/R0010131.JPG')` | sky05 |
| 背景 6 | `asset('cg/R0010143.JPG')` | sky06 |

---

## 7. ゲームフロー

```
[ロード] → auto-enter-vr → VR 入場
  │
  ▼
START MENU
  │  ← START クリック / touch
  ▼
Stage 1 (10s, sky01, BGM S1)
  ├─ モデル出現（位置1〜6 ランダム, anime01, 出現音）
  ├─ [射撃 → ヒット] → anime02 → フェードアウト → 0.5s → 再出現
  ├─ [射撃 → ミス] → コンボリセット
  └─ タイムアップ → 暗転 → 次ステージ（次ステージ背景・BGM プリロード）
  ▼
Stage 2 (12s, sky02, BGM S1)  ← 同上
  ▼
Stage 3 (12s, sky03, BGM S2)  ← 同上
  ▼
Stage 4 (12s, sky04, BGM S2)  ← 同上
  ▼
Stage 5 (12s, sky05, BGM S3)  ← 同上
  ▼
Stage 6 (12s, sky06, BGM S3)  ← 同上
  ▼
Stage 7 (12s, sky01, BGM S4) ← リザルト
  ├─ タイマー停止
  ├─ 位置1にモデル出現（anime01）
  ├─ スコア POST
  ├─ TOP5 GET & 表示
  ├─ Particle 表示
  ├─ 英語メッセージ表示
  └─ 12秒後 → フェードアウト → VR 退出 → location.reload()
```

---

## 8. 前提条件（仮定）

| # | 仮定 | 根拠 |
|---|------|------|
| A1 | ボールに重力 2.45 適用 | halloween4 と同じ挙動 |
| A2 | ボール速度 20 units/sec | halloween4 と同じ |
| A3 | 同時進行ボール最大 2 発 | halloween4 と同じ |
| A4 | ミス時: コンボリセットのみ（減点なし） | シンプルさ |
| A5 | スコア API: `api/findhoufu-scores` | halloween4 パターン踏襲 |
| A6 | ユーザー識別: `name: 'noName'` | 後でセッション連携 |
| A7 | 6 位置は findakaei の値をそのまま使用 | 要件指定 |
| A8 | モデルは 1 つのみ（同時に複数出現しない） | 要件フロー |
| A9 | Stage7 は射撃不可（リザルト表示のみ） | 要件フロー |

---

## 9. ファイル構成（予定）

```
resources/views/findHoufu/
├── index.blade.php        → HTML シェル + @include
├── _components.blade.php  → JS: 設定 + A-Frame コンポーネント + ユーティリティ
├── _scene.blade.php       → A-Frame シーン定義（a-scene, a-assets, エンティティ）
├── analysis_akaei_qwen3.8.md    → 分析書（既存）
└── analysis_halloween4_qwen3.8.md → 分析書（既存）
```
---

## 10. 追加要件（2026-09-04）

shooting3Dhalloween4 を参考に、以下の仕様変更を追加する。

### 10.1 ボール速度 50%

| # | 要件 | 現状 | 目標 |
|---|------|------|------|
| R1 | ボール速度を現在の50%に | `this.speed = 30` | `this.speed = 15` |

### 10.2 モデルサイズ 50%

| # | 要件 | 現状 | 目標 |
|---|------|------|------|
| R2 | モデル（Bucchi）サイズを現在の50%に | `0.7 / 1.35 / 0.7 / 1.05 / 0.5 / 0.95` | `0.35 / 0.675 / 0.35 / 0.525 / 0.25 / 0.475` |

### 10.3 各ステージ16秒

| # | 要件 | 現状 | 目標 |
|---|------|------|------|
| R3 | 全ステージ（Stage1〜7）の時間を16秒に | `timeLimit: 12` | `timeLimit: 16` |

### 10.4 ボールヒット時：跳ね返り+フェードアウト

| # | 要件 | 現状 | 目標 |
|---|------|------|------|
| R4 | モデルヒット時、ボールが少し跳ね返りながらフェードアウト（shooting3Dhalloween4同等） | 即削除+プール返却（視覚効果なし） | emissiveフラッシュ → scale 0へ300msアニメ → プール返却 |

**詳細（shooting3Dhalloween4 参照）:**
1. `emissive = 0xFFFFFF, intensity = 1.5` でフラッシュ
2. `animation__fade: { property: 'scale', to: '0 0 0', dur: 300, easing: 'easeInQuad' }`
3. 300ms後に `releaseBall()`

### 10.5 VR自動入場（維持）

| # | 要件 | 現状 | 目標 |
|---|------|------|------|
| R5 | VRゴーグルでページを開いた際に自動VR入場 | `auto-enter-vr` 実装済み | 変更なし（維持） |

### 10.6 Stage1〜6: 同一BGMループ（ステージ切替で停止しない）

| # | 要件 | 現状 | 目標 |
|---|------|------|------|
| R6 | Stage1〜6はすべて同一BGM（`bgm_s1`）をループ再生。ステージ切替で停止・切替しない | Stage1-2: bgm_s1, Stage3-4: bgm_s2, Stage5-6: bgm_s3（crossfade） | Stage1〜6: すべて `bgm_s1` ループ。Stage7到達時のみ `bgm_s4` に切替 |

### 10.7 Stage7: 16秒後に自動VR解除

| # | 要件 | 現状 | 目標 |
|---|------|------|------|
| R7 | Stage7（リザルト）で16秒経過後、自動でVRモードを解除しリセット | 12秒後VR解除 | 16秒後VR解除（`exitToStart` 維持） |

### 10.8 成功基準

- [ ] ボール速度が30→15に变更
- [ ] モデルサイズが各50%に缩小
- [ ] 全ステージ16秒
- [ ] ボールヒット時: emissiveフラッシュ + scaleフェードアウト（300ms）
- [ ] Stage1〜6: BGMが途切れずループ再生
- [ ] Stage7: 16秒後にVR解除+リセット
- [ ] VRゴーグルで自動VR入場（既存機能維持）
