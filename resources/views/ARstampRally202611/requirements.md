# ARstampRally202611 要件定義

> 作成日: 2026-10-27 / **改訂: 2026-10-28（景品仕様を「お菓子ガラポン 4 段階」＋「市内共通商品券抽選応募」へ変更）**
> 状態: **仕様確定**（ガラポンは LocalStorage 管理・商品券応募は 1 メール 1 回をサーバー側で強制・応募後の倍率昇格は `check-status` で自動）
> 本ファイルの内容確定後に `design.md` → `tasks.md` の順で作成する。

## 1. 目的
2026年11月開催の月次 AR スタンプラリーキャンペーン。
ARstampRally202610 の**完成形**（iOS カメラ起動の根本修正＋Service Worker によるオフライン対応を織り込んだ状態）を
クローンとして新規追加し、既存キャンペーン（202603 / 202605 / 202606 / 202609 / 202610）への影響をゼロとする。

## 2. 前提方針
| # | 方針 |
|---|---|
| P-1 | 202610 で後から積み上げた改修（iOS カメラ誤表示の根本修正・オフライン対応）を**初期実装に内包**する（後付け改修フェーズを設けない） |
| P-2 | 既存キャンペーン、共有JS（`public/js/`）、`public/service-worker.js`、`public/sw-stamp202610.js`、DB スキーマは一切変更しない（既存ファイルへは追記のみ） |
| P-3 | アセットは当面 `public/cg/202610/` を参照し、**単一切替点**で `public/cg/202611/` に切り替えられる構造にする（利用者決定・2026-10-27） |
| P-4 | 既存 `marker_scans` / `prize_exchanges` は**変更も流用もしない**（202611 の行を作らない）。202611 専用の新規テーブルを 1 枚追加する（`stamp202611_voucher_applications`）。ガラポン引抽回数は LocalStorage のみで管理しサーバー記録しない（利用者決定・2026-10-28） |
| P-5 | PSR-1 / PSR-2 / PSR-12・Laravel 11 規約。PHP 変更ごとに `php -l` で構文検証 |

## 3. 機能要件（ラリー本体）
| # | 要件 |
|---|---|
| FR-1 | `GET /stamp202611` で参加ページを表示（202610 と同一の UI・操作） |
| FR-2 | マーカー走査（`capture_type=marker_scan`）とボール投擲（`capture_type=ball_hit`）でスタンプを集め、収集状態をクライアント（LocalStorage / IndexedDB）に保持する |
| FR-3 | スキャン実績を `POST /stamp202611/record-scan` でサーバー記録する。当日重複はクライアント側 `marker-scan-cache-202611-{markerId}-{YYYY-MM-DD}` とサーバー側 `whereDate('scanned_at', today)` の二重で去重する |
| FR-4 | 収集種数が **5 / 10 / 15 / 20 種**に到達するごとに、お菓子のガラポンを 1 回引ける（合計最大 4 回）。アプリ内にくじ引き機能は持たず、ボタンは「会場内のガラポンを引く権利」の記録のみ（→ §3-2 FR-37〜FR-40） |
| FR-5 | 収集種数 **10 種以上**で市内共通商品券の抽選に応募できる。`POST /stamp202611/apply-voucher` で email / name を DB に保存し、**1 メールアドレス 1 回のみ**をサーバー側で強制する（→ §3-2 FR-41〜FR-44） |
| FR-6 | レートリミッターで不正連打を防止する（`stamp202611_scan`・`stamp202611_check` = 分120回、`stamp202611_apply` = 時10回。キーはセッションの `ar_fingerprint_202611`、未設定時は IP） |
| FR-7 | API は `web` ミドルウェアグループ（CSRF + セッション有効）に配置し、セッションに `ar_fingerprint_202611` を保持して record-scan → check-status → apply-voucher を同一識別で結ぶ |
| FR-8 | 202611 では景品コード（`prize_code`）を発行しない（`prize_exchanges` は使用しない）。抽選応募者は管理画面から CSV（名前・メールアドレス・スタンプ数・当選倍率）でダウンロードし業者へ提出する（FR-45） |

## 3-2. 機能要件（景品仕様: お菓子ガラポン / 市内共通商品券抽選）
> 202610 の「10 種以上で景品コード 1 枚と交換」は**廃止**。202611 は次の 2 系統のみとする（利用者決定・2026-10-28）。
> 「スタンプ N 個」= そのユーザーが収集した**動物の種数**（`COUNT(DISTINCT marker_id)`、`model_01`〜`model_20` の 20 種）。

### 景品 A: お菓子（ガラポン）— 4 段階・最大 4 回
| # | 要件 |
|---|---|
| FR-37 | 収集種数が **5 / 10 / 15 / 20 種**に到達するごとに 1 回、お菓子のガラポンを引ける（合計最大 4 回）。お菓子の種類は**会場内のガラポンを実際に回して**決定する。アプリ内にくじ引き・抽選・くじ結果表示機能は**作らない** |
| FR-38 | 「ガラポンを引く」ボタンは**スタンプ帳（コレクション）内**に配置し、到達済み段階でのみ有効にする。押下済み段階は LocalStorage `ar-gacha-draws-202611`（到達済み段階の配列。例 `[5,10]`）で管理し、**4 回を超えたらボタンを無効化**する |
| FR-39 | ガラポンの回数・段階は**サーバーへ送信しない**（厳密な重複防止は行わない。ブラウザデータ削除でリセットされうることは仕様として許容し、会場での配布は運営が目視で管理する） |
| FR-40 | ボタン押下後は「会場スタッフにこの画面を見せてガラポンを回してもらう」旨の案内を表示する。段階値 5/10/15/20 は `config/ar202611.php` の `candy_tiers` から blade 経由で注入する（JS 直書き禁止・FR-17 と同一方針） |

### 景品 B: 市内共通商品券抽選 — 10 種以上で応募 / 15・20 種で倍率
| # | 要件 |
|---|---|
| FR-41 | 収集種数 **10 種以上**でスタンプ帳（コレクション）内に**抽選応募ボタン**を表示し、email / name を入力させて `POST /stamp202611/apply-voucher` で応募情報を DB に保存する |
| FR-42 | 応募完了時の画面表示は収集種数に応じて次とする（10〜14 種）「市内共通商品券の抽選会に応募しました。」／（15〜19 種）「市内共通商品券の抽選会に応募しました。（15個以上集めたので当選確率2倍です。）」／（20 種）「市内共通商品券の抽選会に応募しました。（20個すべて集めたので当選確率3倍です。）」 |
| FR-43 | 当選倍率 `weight`（1 / 2 / 3）は**サーバー側で算出**して保存する（10〜14 種 → 1、15〜19 種 → 2、20 種 → 3）。クライアントが送る weight は無視する。応募後に種数が増加した場合は、**`check-status`（ページ読み込み時）でサーバーが `stamp_count` / `tier` / `weight` を自動で上方更新**する（下方更新しない。利用者が応募ボタンを再押下する必要はない。`apply-voucher` の冪等再呼び出しでも同じ更新を行う）。応募者には FR-42 の文言で現在倍率を表示する（利用者決定・2026-10-28） |
| FR-44 | **1 メールアドレス 1 回のみ**を DB の UNIQUE 制約とサーバー側検証で強制する。email は前後空白除去 + 小文字化して保存・比較する。同一 email が既存の場合は HTTP 422（`already_applied`）を返し「すでに応募済みです」を表示する。`fingerprint` 単位でも 1 行のみ（UNIQUE） |
| FR-45 | 管理画面 `GET /admin/dashboard202611` に応募一覧と **CSV ダウンロード**（`GET /admin/export-csv?dataset=voucherApplications202611`）を設置し、`name, email, stamp_count, weight, applied_at` を出力する。抽選・当選連絡は業者側で行う（アプリ側は抽選しない） |
| FR-46 | 応募はオンライン必須（オフラインでは行わない）。応募直前にスキャンキューをフラッシュし CSRF トークンを更新する（FR-26 / FR-27 と同一手順） |

## 4. 機能要件（iOS カメラ起動・202610 §7/§8 の確定形を初期実装）
| # | 要件 |
|---|---|
| FR-9 | カメラ `<video>` の取得セレクタは `document.querySelector('video')` とする（`#ar-scene video` は使用しない）。AR.js は camera video を `document.body` 直下に append するため、`#ar-scene video` は常に `null` となる（202610 で確認済みの根本原因） |
| FR-10 | セレクタ指定は `monitorCameraStartup` / `ensureCameraAccess` / `getUserMedia` 成功ハンドラの**3 箇所すべて**に適用する |
| FR-11 | カメラ監視はタイムアウト到達後も `setInterval` を解除せず継続し、video が ready になった時点で `#camera-error` / `#camera-help-modal` / `.arjs-loader` を自動非表示にする（誤表示の自動解消） |
| FR-12 | リトライ時、`video.srcObject` が既に存在する場合は新たな `getUserMedia` を要求せず `video.play()` のみを試行する（iOS のストリーム競合 `NotAllowedError` / 無限リトライの回避） |
| FR-13 | 監視タイムアウトおよびローダー強制非表示のフォールバック値は UA 判定で **iOS = 15000ms / その他 = 7000ms** とする |
| FR-14 | `#camera-error` の文言は「起動に時間がかかる場合がある／画面をタップすると再開されることがある」等、誤表示時に正しい導線を与える表現とする（要素 ID・構造は 202610 と維持） |

## 5. 機能要件（識別子の分離・再交換）
| # | 要件 |
|---|---|
| FR-15 | クライアント保存キーはすべて 202611 専用とし、**サフィックスなしで開始**する: LocalStorage `ar-stamp-rally-202611` / `ar-captured-animals-202611` / `ar-gallery-selection-202611` / `ar-user-id-202611` / `ar-gacha-draws-202611`（ガラポン到達済み段階） / `ar-voucher-applied-202611`（応募状態キャッシュ） / `ar-camera-reload-202611` / `ar-scan-queue-202611`、IndexedDB `ARStampRallyDB202611`、Cookie `ar_user_id_202611` |
| FR-16 | 新キーにより 202611 は新 userId → 新 fingerprint で開始されるため、202610 まで参加済みのブラウザでも 202611 は「ガラポン 0 回・未応募」として開始できる（202610 §9 の `-r2` と同等の効果が最初から成立）。`prize_exchanges` / `marker_scans` の既存レコードは削除・更新しない（履歴保持） |
| FR-17 | 同一キャンペーン内で再度のリセットが必要になった場合のみ、202610 と同じ運用でキーに `-r2` を付与できる構造とする（本フェーズでは実施しない） |
| FR-18 | console ログプレフィックスは `[AR202611]`、JS 関数・変数名は 202611 サフィックス（`getUserId202611` / `CookieHelper202611` / `generateUUID202611` 等）とし、202610 のグローバルと衝突させない |

## 6. 機能要件（オフライン対応・202610 §10 の確定形を初期実装）
| # | 要件 |
|---|---|
| FR-19 | 202611 専用 Service Worker `public/sw-stamp202611.js` を導入し、`/stamp202611` ページで登録する。キャッシュキーは `ar202611-{html,core,assets}-v1`。既存 SW（`public/service-worker.js` / `public/sw-stamp202610.js`）とそのキャッシュは変更・削除しない |
| FR-20 | install 時に「最小必須セット」を事前キャッシュする: ページ HTML / オフラインページ / 共有JS（`ar-engine.min.js` `ar-tracking.min.js` `geo-location.min.js` `loaders/GLTFLoader.js` `loaders/OBJLoader.js`）/ `css/stampRally.css` / マーカー全枚（`pattern-maker00..20.patt`）/ ロード中モデル `Model_00.glb` / `objects/ball.obj` `ball.mtl` / `sounds/loading.ogg` / `img/howToOperate.png` |
| FR-21 | 全 GLB（202610 参照時は計 59MB）は install 時に事前キャッシュしない。初回オンライン取得時（`lazy-model` の pre-fetch 経由）に assets キャッシュへ保存し、以降オフラインでも表示可能にする |
| FR-22 | 取得戦略: navigation（ページ）= network-first → キャッシュ → オフラインページ → 504。静的アセット（`js/` `css/` `img/` `build/` `cg/` 配下）= cache-first + stale-while-revalidate。API POST 3 種・外部オリジン・`/stamp202611/csrf` は SW が介入しない |
| FR-23 | オフライン専用ページ `GET /stamp202611/offline`（外部アセット依存なしのインライン CSS/JS・`Cache-Control: no-store`・`X-Robots-Tag: noindex`）を追加し、SW の最終フォールバックとする。収集済み種別数・未送信スキャン数・接続状態を表示し、「AR を続ける（キャッシュ）」「再試行」「キャッシュ削除」を提供する |
| FR-24 | オフライン中のスキャン記録は LocalStorage キュー `ar-scan-queue-202611`（上限 200 件、超過は最古を破棄）に蓄積し、`online` イベントと次回ロード時に自動再送する。各要素は送信 body そのものを保持し、再送時は元の `scannedAt` を維持する（記録日時が現在時に化しない）。HTTP 4xx/5xx は再送しない。再送失敗時はキューへ戻して打ち切る |
| FR-25 | オフライン中は `#offline-banner`（文言は `#offline-banner-text`）を表示し、オンライン復帰で自動非表示にする。未送信キュー件数も文言に反映する |
| FR-26 | キャッシュ済み HTML の CSRF トークン陳腐化（セッション失効 → 419）に備え、`GET /stamp202611/csrf`（`no-store`）でトークンを再取得できること。オンライン復帰時・キュー再送時・抽選応募前に更新する |
| FR-27 | 商品券抽選の応募（apply-voucher）はオンライン必須（オフラインでは行わない）。応募直前に必ずスキャンキューをフラッシュし、CSRF トークンを更新してから応募リクエストを送る。ガラポンボタンはオフラインでも押下可（LocalStorage のみ・サーバー通信なし） |
| FR-28 | デバッグ導線: URL に `?sw=0` を付けると SW 登録をスキップ。オフラインページの「キャッシュ削除」は `ar202611-*` のみを消去する（`activate` / `CLEAR_CACHES` でも 202611 以外に触れない） |

## 7. 管理ダッシュボード
| # | 要件 |
|---|---|
| FR-29 | `GET /admin/dashboard202611`（auth + admin ミドルウェア）で集計を表示する。集計期間は **2026-11-01 00:00:00 〜 2026-11-30 23:59:59（JST、UTC 変換してクエリ）**。期間値は `config/ar202611.php` の `start` / `end` から取得する（FR-35） |
| FR-30 | 集計項目: 動物別スキャン数（`marker_scan` / `ball_hit`）・個別ユーザー数（`fingerprint`）・最近のスキャン履歴・日別統計（スキャン数／個別ユーザー数）・**抽選応募実績**（応募者数・倍率 1 / 2 / 3 の内訳・最近の応募一覧） |
| FR-31 | 動物リストは `model_01`〜`model_20`（202610 と同一。アセット流用中は動物名リストも同一） |
| FR-47 | 応募一覧は検索（名前 / email / 倍率）とページネーション（20 件）を持つ。個人情報（email・name）を CSV / 画面で出力するのは **admin 認証下のみ**とする |
| FR-48 | CSV ダウンロードは `GET /admin/export-csv?dataset=voucherApplications202611`（既存 `exportCsv` の dataset 分岐に追加）で提供し、`name, email, stamp_count, weight, applied_at` を UTF-8（BOM 無し・`fputcsv`）で出力する。期間絞り込み（`start` / `end`）は `applied_at` に適用する |

## 8. アセット切替の単一点（本キャンペーンの重要要件）
### 8.1 方針
202611 は当面 `public/cg/202610/` を参照して動作させ、後日 `public/cg/202611/`（同名ファイル）へ切り替える。
切替は**設定 1 箇所 + SW の 1 行**で完結させ、blade / controller / admin の再実装を不要とする。

### 8.2 設定（新規 `config/ar202611.php`）
| キー | 既定値 | 使途 |
|---|---|---|
| `asset_dir` | `env('AR202611_ASSET_DIR', '202610')` | `cg/{dir}/` のモデル・マーカー参照（blade 全体） |
| `marker_count` | `20` | `maker01..20` のループ上限（`maker00` はロード中モデル） |
| `candy_tiers` | `[5, 10, 15, 20]` | お菓子ガラポンの到達段階（FR-37 / FR-40）。blade 経由で JS に注入し、到達済み段階は `ar-gacha-draws-202611` で管理 |
| `voucher_threshold` | `10` | 商品券抽選に応募できる最低種数（サーバー側強制・FR-41） |
| `voucher_weights` | `[10 => 1, 15 => 2, 20 => 3]` | 収集種数ごとの当選倍率。サーバーが算出して `weight` に保存（FR-43） |
| `start` / `end` | `2026-11-01 00:00:00` / `2026-11-30 23:59:59` | 管理ダッシュボードの集計期間（JST） |

### 8.3 機能要件
| # | 要件 |
|---|---|
| FR-32 | blade 側のアセット参照は `config('ar202611.asset_dir')` 経由とし、`cg/202611` への変更で blade を再編集しない（`scene.blade.php` の pattern / lazy-model、`js-stamps.blade.php` の `STAMPS[].model`） |
| FR-33 | `public/sw-stamp202611.js` は先頭 1 行の定数 `ASSET_DIR` のみでディレクトリを持つ。静的アセットの cache-first 判定は `cg/` 配下全般（`cg/202610/` と `cg/202611/` の両方）に対して機能させ、切替時にフェッチ戦略を変更不要とする |
| FR-34 | 閾値・倍率・ガラポン段階は `StampRally202611Controller` 内で `config('ar202611.voucher_threshold')` / `voucher_weights` / `candy_tiers` から取得する（PHP の `const` は config を初期化式に使えないため静的メソッドで解決する）。クライアント側の `VOUCHER_THRESHOLD` / `CANDY_TIERS` は blade 経由で注入された同一値のみを用いる |
| FR-35 | 集計期間・マーカー数も同様に config 経由とし、期間変更を config（または `.env`）の 1 箇所変更で完結させる |
| FR-36 | 切替手順（`cg/202611` 配置 → `config/ar202611.php` の `asset_dir` 変更 → `sw-stamp202611.js` の `ASSET_DIR` 変更 → SW バージョン `v1` を `v2` に更新）を README / tasks にチェックリストとして明記する |

## 9. 非機能要件・制約
- **プラットフォーム**: Android は Chrome（WebXR）、iOS は Safari（AR.js / Three.js 実装、必要時 USDZ Quick Look フォールバック）で 202610 と同等に動作すること（ARstampRally 横断ルール）。
- **分離性**: 202610 含む既存キャンペーンの blade / controller / route / RateLimiter / SW は変更しない。`routes/web.php`・`AppServiceProvider`・`AdminController` へは**追記のみ**行う。
- **DB**: 202611 専用の新規テーブル `stamp202611_voucher_applications` を 1 枚追加する（`php artisan migrate`）。既存 `marker_scans` / `prize_exchanges` は**スキーマ変更なし・202611 の行も作らない**。個人情報（email・name）は 202611 専用テーブルにのみ保存する。
- **パフォーマンス**: 初回ロードで全 GLB を一括取得しない（`lazy-model` の遅延取得を維持）。
- **セキュリティ**: CSRF + セッション、サーバー側閾値強制、レートリミット、オフラインページの `noindex`、API レスポンスに個人識別情報を含まない。
- **コード規約**: PSR-1 / PSR-2 / PSR-12、Laravel 11 構造（ルートは `routes/web.php`、設定は `config/` または `.env`）。

## 10. 未確定事項（design.md 作成前に確定したい項目）
> 2026-10-28 時点: **Q-1（期間）／Q-2（閾値）／Q-5（景品仕様）／Q-11（倍率の自動昇格）は確定**。Q-3・Q-4・Q-6・Q-7 は「202610 の踏襲」を前提に design.md / tasks.md を作成した。
> **未確定は Q-8（4 会場の扱い）／Q-9（2 日間開催と集計期間）／Q-10（CSV の文字コード）のみ**（いずれも config または blade 局所変更で吸収できる範囲）。
> 変更する場合は該当 FR と design.md の該当節を改修する（いずれも config または blade 局所変更で吸収できる範囲）。
| # | 項目 | 本ドラフトの仮値 | 備考 |
|---|---|---|---|
| Q-1 | イベント期間 | **確定（2026-10-27）**: 2026-11-01 00:00:00 〜 2026-11-30 23:59:59 JST で実装開始。`config/ar202611.php` の `start` / `end`（または `.env`）1 箇所の変更で後から確定する | 利用者決定・2026-10-27（FR-35） |
| Q-2 | 景品閾値 | **確定（2026-10-28）**: ガラポン 5 / 10 / 15 / 20 種、商品券応募 10 種以上（倍率 15 種 2 倍・20 種 3 倍） | `config/ar202611.php` の `candy_tiers` / `voucher_threshold` / `voucher_weights` |
| Q-3 | 参加種数 | マーカー 21 枚（00〜20）／動物 20 種（model_01〜20） | 202610 踏襲 |
| Q-4 | 保存キーの初期形 | サフィックスなし（`...-202611`） | リセットが必要になった時点で `-r2` を追加 |
| Q-5 | ~~景品内容・交換導線~~ | **解決済み（2026-10-28）**: 景品コードは廃止。お菓子ガラポン 4 段階（LocalStorage 管理・サーバー記録なし）＋ 商品券抽選応募（1 email 1 回・DB 保存・CSV 出力） | FR-37〜FR-48 |
| Q-6 | `cg/202611` のファイル名規則 | `Model_NN.glb` / `pattern-makerNN.patt`（202610 と同名） | 異なる命名になる場合 FR-32/33 を再設計 |
| Q-7 | 202610 の既知未対応（202609 の `#ar-scene video`） | 本キャンペーンでは対応しない（分離原則） | 202611 では最初から正しいセレクタで実装 |
| Q-8 | 4 会場の扱い | **未確定**: アプリ側で会場を区別しない（ガラポンは LocalStorage、応募は 1 email 1 回のため会場列は不要と判断）。会場別の配布実績が必要なら `venue` 列と選択 UI を追加する | 必要なら FR-38 / FR-48 を改修 |
| Q-9 | 2 日間開催と集計期間 | **未確定**: 既定は 2026-11-01 〜 2026-11-30。2 日間開催に合わせる場合は `config/ar202611.php` の `start` / `end` のみ変更（コード変更不要） | 開催日確定後に config 更新 |
| Q-10 | CSV の文字コード | **未確定**: 既定は UTF-8（BOM 無し・既存 `exportCsv` と同一）。業者が Excel で直接開くなら BOM 付き UTF-8 へ変更が必要 | 業者側の読み込み仕様が決まり次第 FR-48 を改修 |
| Q-11 | 応募後の倍率更新 | **確定（2026-10-28 利用者回答 A）**: 応募後に 15 / 20 種へ到達したら `check-status`（ページ読み込み）だけで `stamp_count` / `tier` / `weight` を**自動上方更新**し、画面にも 2 倍 / 3 倍を自動表示。応募ボタンの再押下は不要（FR-43 / AC-13） | 応募時点固定へ変更する場合は FR-43 を改修 |

## 11. 受け入れ条件（検証計画）
| # | 条件 |
|---|---|
| AC-1 | `php -l` が新規・変更 PHP すべてで通過する |
| AC-2 | `php artisan route:list` に 202611 の 6 ルート（index / offline / csrf / record-scan / check-status / apply-voucher）＋ admin（dashboard202611 / export-csv）が存在し、既存キャンペーンのルートに変化がない |
| AC-3 | 202611 の blade / JS に `202610` 識別子（LocalStorage キー・API URL・console プレフィックス・SW 名）が残存していない |
| AC-4 | SW のフェッチ戦略（navigation network-first / 静的 cache-first / API 非介入 / 外部オリジン非介入 / offline フォールバック）がシミュレーション検証で全 PASS |
| AC-5 | スキャンキュー（上限 200・最古破棄・`scannedAt` 維持・4xx/5xx 非再送・online 復帰再送・応募前フラッシュ）がシミュレーション検証で全 PASS |
| AC-6 | 10 種未満の apply-voucher が 422 となる／クライアントが送る weight は無視されサーバー算出値が保存される（改ざんケース） |
| AC-7 | `git status` で 202610 含む既存キャンペーンファイルの未変更を確認（追記は routes / AppServiceProvider / AdminController / マイグレーション追加のみ。`marker_scans` / `prize_exchanges` のスキーマ変更なし） |
| AC-8 | 実機: Android Chrome と iOS Safari（低スペック機含む）でカメラ起動・マーカー認識・ボール投擲・ガラポンボタン・抽選応募が動作し、オフライン（機内モード）で AR 継続とキュー再送が動作する |
| AC-9 | ガラポン: 5 / 10 / 15 / 20 到達時に各 1 回押下可。`ar-gacha-draws-202611` に記録され、到達済み段階は再押下不可・4 回目でボタン無効化 |
| AC-10 | 応募: 10 種未満はボタン非表示。email 重複（前後空白・大小文字の違いを含む）で 422 `already_applied` → 「すでに応募済みです」表示。15 / 20 到達時に「当選確率2倍 / 3倍」の文言が表示される |
| AC-11 | `stamp202611_voucher_applications` の UNIQUE（email / fingerprint）が DB 側で機能し、`php artisan migrate` / `migrate:rollback` が既存テーブルへ影響しない |
| AC-12 | 管理画面の CSV ダウンロードが `name, email, stamp_count, weight, applied_at` を全応募行出力し、期間絞り込みが動作する |
| AC-13 | 倍率の自動昇格: 10 種で応募後に 15 種へ到達 → ページを読み込むだけ（`check-status` のみ、応募ボタンは再押下しない）で DB の `stamp_count` が 15・`weight` が 2 に更新され「当選確率2倍」表示。20 種で 3 倍。種数が増えない限り下方更新しない |
