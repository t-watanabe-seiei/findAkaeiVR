# ARstampRally202611

2026年11月開催予定の AR スタンプラリー。ARstampRally202610 の**完成形**（iOS カメラ起動の根本修正＋
Service Worker によるオフライン対応を織り込んだ状態）をクローンとして新規追加する。
3Dモデル・マーカーアセットは当面 `public/cg/202610/` を参照し、設定 1 箇所で `public/cg/202611/` へ切り替える導線を持つ。

## 概要
- AR（マーカー走査 / ボール投擲）で隠れたキャラクター（20種 + Model_00）を捕まえるスタンプラリー
- **お菓子のガラポン**: 5 / 10 / 15 / 20 種到達ごとに 1 回、会場内のガラポンを引く（最大 4 回）。アプリ内にくじ引き機能は持たず、スタンプ帳（コレクション）内のボタン（最大 4 回押下）と会場スタッフへの提示用画面のみ。引抽回数は LocalStorage で管理
- **市内共通商品券の抽選**: 10 種以上で email / name を入力して応募（**1 メールアドレス 1 回**・サーバー側で強制）。15 種で「当選確率 2 倍」、20 種で「当選確率 3 倍」を画面に表示。応募者一覧は CSV でダウンロードし業者（抽選・当選連絡）へ提出
- オフライン（通信不可）でも AR を継続でき、スキャン記録はキューに蓄積して自動再送する（抽選応募はオンライン必須）
- 集計・抽選応募実績は管理ダッシュボードで確認可能

## 現在の状態（2026-10-28 時点）
- **ドキュメント3点 + README 確定**。`requirements.md`（FR-1〜FR-48 / AC-1〜AC-13）・`design.md`（§1〜§11）・`tasks.md`（T1〜T47 / Phase 1〜7 ＋ Phase 3B）。
- 仕様の確定状況: **Q-1（期間）／Q-2（景品閾値）／Q-5（景品仕様）／Q-11（倍率の自動昇格: `check-status` で自動）は確定**。
  **未確定: Q-8（4 会場の扱い）／Q-9（2 日間開催に集計期間を合わせるか）／Q-10（CSV の文字コード）**。
- **コード実装は未着手**（Phase 2〜7）。`resources/views/ARstampRally202611/` の blade、Controller、SW、`config/ar202611.php`、抽選応募テーブルのマイグレーションはいずれも未作成。
  設計確定前に作っていたドラフト 4 ファイルは 2026-10-28 に削除済み（下記「更新内容」参照）。**DB へのマイグレーションは未実行**。

## ルート（予定）
| メソッド | URI | 説明 |
|---|---|---|
| GET | `/stamp202611` | 参加ページ |
| GET | `/stamp202611/offline` | オフライン専用ページ（SW の最終フォールバック・noindex） |
| GET | `/stamp202611/csrf` | CSRF トークン再取得（no-store） |
| POST | `/stamp202611/record-scan` | スキャン記録（分120回制限） |
| POST | `/stamp202611/check-status` | 収集種数・ガラポン到達段階・抽選応募状態の確認（分120回制限） |
| POST | `/stamp202611/apply-voucher` | 市内共通商品券の抽選応募（時10回制限・10 種以上・1 メール 1 回・倍率 1/2/3 はサーバー算出） |
| GET | `/admin/dashboard202611` | 管理ダッシュボード（期間 2026-11-01 〜 2026-11-30 JST・予定）＋ 抽選応募一覧 |
| GET | `/admin/export-csv?dataset=voucherApplications202611` | 業者提出用 CSV（`name, email, stamp_count, weight, applied_at`） |

## 予定しているファイル構成
- `resources/views/ARstampRally202611.blade.php` — エントリ（新規）
- `resources/views/ARstampRally202611/` — パーシャル11（head / ui / scene / aframe-components / js-stamps / js-prize / js-throw / js-gallery / js-camera / js-init / **offline**）（新規）
- `resources/views/ARstampRally202611/requirements.md` — 要件定義（**作成済み**）
- `resources/views/ARstampRally202611/design.md` — 設計書 §1〜§11（**作成済み**）
- `resources/views/ARstampRally202611/tasks.md` — 作業タスク T1〜T47 / Phase 1〜7 ＋ Phase 3B（**作成済み**・Phase 1 のみ完了）
- `public/sw-stamp202611.js` — 202611 専用 Service Worker（新規・予定）
- `app/Http/Controllers/StampRally202611Controller.php` — スキャン記録 / 状態確認 / 抽選応募 API（新規・予定）
- `app/Models/Stamp202611VoucherApplication.php` — 抽選応募モデル（新規・予定）
- `database/migrations/2026_11_XX_XXXXXX_create_stamp202611_voucher_applications_table.php` — 抽選応募テーブル（`email` / `fingerprint` UNIQUE・新規・予定）
- `config/ar202611.php` — アセットディレクトリ / ガラポン段階 / 応募閾値 / 当選倍率 / 期間の単一切替点（新規・予定）
- `routes/web.php` / `app/Providers/AppServiceProvider.php` / `app/Http/Controllers/AdminController.php` — 追記のみ（予定）

## 202611 固有の識別子（分離・予定）
- LocalStorage: `ar-stamp-rally-202611` / `ar-captured-animals-202611` / `ar-gallery-selection-202611` / `ar-user-id-202611` / `ar-scan-queue-202611` / **`ar-gacha-draws-202611`**（ガラポン引抽済み段階） / **`ar-voucher-applied-202611`**（応募状態キャッシュ）
- IndexedDB: `ARStampRallyDB202611`、Cookie: `ar_user_id_202611`
- スキャン日次キャッシュ: `marker-scan-cache-202611-*`
- Service Worker / キャッシュ: `/sw-stamp202611.js` / `ar202611-{html,core,assets}-v1`
- レートリミッター: `stamp202611_scan` / `stamp202611_check` / `stamp202611_apply`

## アセット（当面 202610 共用）
- モデル: `public/cg/202610/Model_00.glb` 〜 `Model_20.glb`
- マーカー: `public/cg/202610/pattern-maker00.patt` 〜 `pattern-maker20.patt`
- 切替は `config/ar202611.php` の `asset_dir`（既定 `202610`）と `sw-stamp202611.js` の `ASSET_DIR` 1 行で行う（FR-32〜FR-36）

## 更新内容

### 2026-10-27 — 要件定義（requirements.md）作成
- `resources/views/ARstampRally202611/requirements.md` を新規作成（§1〜§11 / FR-1〜FR-36 / AC-1〜AC-8）。
- ベースラインは ARstampRally202610 の完成形（`requirements.md` §1〜§10、`design.md` §1〜§10、コミット `stamp20261027_01 offline service worker`）。
  202610 で後付けした iOS カメラ誤表示の根本修正（video セレクタ / 監視継続 / 15000ms）とオフライン SW 対応を
  **初期実装に内包する**方針を FR-9〜FR-14 / FR-19〜FR-28 として明文化した。
- アセット参照方針を利用者決定により確定: **当面 `cg/202610` を参照し、後日 `cg/202611` へ単一切替点で切り替える**
  （§8 / FR-32〜FR-36、新規 `config/ar202611.php` + SW 先頭 `ASSET_DIR` 1 行）。
- 保存キーはサフィックスなしで開始（FR-15）。新 userId → 新 fingerprint により、202610 まで交換済みのブラウザでも
  202611 は未交換で開始できる（202610 §9 の `-r2` と同等効果が最初から成立、FR-16）。
- 未確定事項 Q-1〜Q-7（イベント期間 / 閾値 / 種数 / 保存キー / 景品 / `cg/202611` の命名 / 202609 側未対応）を §10 に列挙。
- 本 README を新規作成（更新内容の記録）。

### 2026-10-27 — 設計書（design.md）・作業タスク（tasks.md）作成／Q-1 確定
- **Q-1（イベント期間）を確定**: 2026-11-01 00:00:00 〜 2026-11-30 23:59:59 JST で実装し、`config/ar202611.php` の `start` / `end`
  （または `.env` の `AR202611_START` / `AR202611_END`）1 箇所の変更で後から確定する（requirements.md §10 Q-1 / FR-29 / FR-35 に反映）。
- `design.md` を作成（§1 アーキテクチャ / §2 ファイル構成 / §3 `config/ar202611.php` 単一切替点 + 切替手順 /
  §4 202610→202611 識別子置換一覧 / §5 API 設計 / §6 管理ダッシュボード / §7 iOS カメラ / §8 オフライン SW /
  §9 保存キーと再交換 / §10 リスク / §11 検証計画）。
  - 202610 の `const PRIZE_EXCHANGE_THRESHOLD = 10` は PHP の定数初期化式で `config()` を使えないため、
    202611 では `private static function threshold(): int` に置き換える方針を明記（design.md §3.2）。
  - SW の静的アセット cache-first 判定を `cg/` 配下全般とし、`cg/202610` → `cg/202611` 切替でフェッチ戦略を変更不要とした（FR-33）。
- `tasks.md` を作成（Phase 1 ドキュメント完了 / Phase 2 基盤 / Phase 3 サーバー側 / Phase 4 管理 /
  Phase 5 iOS カメラ / Phase 6 オフライン / Phase 7 最終検証、T1〜T38。各 Phase に検証タスクを対応させた）。
- 202610 で後付けした 4 改修（iOS 安全策 / video セレクタ根本修正 / 保存キー `-r2` 化 / オフライン SW）と
  202611 での扱いの対応表を tasks.md 末尾に記載。

### 2026-10-28 — 景品仕様を「お菓子ガラポン 4 段階」＋「市内共通商品券抽選」へ改訂
利用者の再提示により 202610 踏襲（10 種で景品コード 1 枚と交換）を**廃止**し、以下の 2 系統へ改訂した。

**景品 A: お菓子のガラポン（requirements §3-2 / FR-37〜FR-40）**
- 5 / 10 / 15 / 20 種到達ごとに 1 回、合計**最大 4 回**。お菓子の種類は**会場内のガラポンを実際に回して**決定する（アプリ内にくじ引き・抽選・くじ結果表示機能は作らない）
- スタンプ帳（コレクション）内に「ガラポンを引く」ボタン。押下済み段階は LocalStorage `ar-gacha-draws-202611` に保存し、4 回でボタン無効化。**サーバーには送信しない**（厳密な重複防止を行わない = 利用者可決）
- 押下後は「会場スタッフにこの画面を見せてガラポンを回してください」の案内を表示

**景品 B: 市内共通商品券の抽選（FR-41〜FR-48）**
- 10 種以上でスタンプ帳内に抽選応募ボタン → email / name を入力 → `POST /stamp202611/apply-voucher` で DB 保存
- 表示文言: 10〜14 種「市内共通商品券の抽選会に応募しました。」／15〜19 種「市内共通商品券の抽選会に応募しました。（15個以上集めたので当選確率2倍です。）」／20 種「市内共通商品券の抽選会に応募しました。（20個すべて集めたので当選確率3倍です。）」
- 倍率 `weight`（1 / 2 / 3）は**サーバーが収集種数から算出**して保存（クライアント送信値は無視）。応募後に 15 / 20 種へ到達した場合は上方更新（Q-11 確定）
- **1 メールアドレス 1 回のみ**を DB の UNIQUE（`email`）＋ サーバー検証（422 `already_applied`）で強制。email は前後空白除去・小文字化して比較
- 管理画面から CSV（`name, email, stamp_count, weight, applied_at`）をダウンロード → 業者が抽選・当選連絡を行う

**DB 方針の変更（P-4 / §9 / design §5.4）**
- 新規テーブル `stamp202611_voucher_applications` を 1 枚追加（`fingerprint` / `email` UNIQUE）。既存 `marker_scans` / `prize_exchanges` は**スキーマ変更なし・202611 の行を作らない**。個人情報は専用テーブルにのみ保存
- 景品コード（`prize_code`）発行は廃止。`exchange-prize` ルートと `stamp202611_redeem` レートリミッターを廃止し、`check-status` / `apply-voucher` / `stamp202611_apply` へ置き換え

**反映ファイル**
- `requirements.md`: P-4 改訂／FR-4〜FR-8・FR-15・FR-16・FR-26・FR-27・FR-30・FR-34 改訂／§3-2（FR-37〜FR-48）新設／§8.2 に config キー追加／Q-2・Q-5 確定＋ Q-8〜Q-11 追加／AC-2・AC-5〜AC-8 改訂＋ AC-9〜AC-12 追加
- `design.md`: §1 アーキテクチャ／§2 ファイル構成／§3 config（`candy_tiers`・`voucher_threshold`・`voucher_weights`）と §3.1・§3.2／§4 識別子／§5 API（5.2 checkStatus・5.3 applyVoucher・**5.4 DDL**・**5.5 ガラポン**・**5.6 CSV**）／§6 管理画面／§8.5／§9／§10 リスク／§11 検証計画
- `tasks.md`: Phase 1 に T5b／Phase 3 改訂（T12〜T15）／**Phase 3B（T39〜T45）新設**／Phase 4 に T46／Phase 7 に T47／検証結果に AC-9〜AC-12 追加
- `README.md`: 概要・ルート表・ファイル構成・識別子・注意・本項

**未確定（要確認）**
- Q-8: 4 会場をアプリで区別するか（現状は会場列なし）
- Q-9: 2 日間開催に合わせ `config` の `start` / `end` を絞るか（現状 11/01〜11/30）
- Q-10: CSV を Excel で開くため BOM 付き UTF-8 が必要か（現状 BOM 無し）

### 2026-10-28 — 仕様再確認（ガラポン LocalStorage / 商品券 1メール1回 / CSV）と Q-11 確定

利用者の仕様再提示と 3 点のドキュメントを照合し、以下の内容が一致していることを確認した。

| 提示された仕様 | 対応要件 | 判定 |
|---|---|---|
| ガラポン 4 回はスマホのローカルストレージで管理し、あまり厳しくチェックしない | FR-38 / FR-39（`ar-gacha-draws-202611`・サーバー送信なし） | 一致 |
| 商品券応募は厳密に 1 メールアドレスにつき 1 回のみ | FR-44（DB の UNIQUE + 422 `already_applied`） | 一致 |
| スタンプ 10 個でスタンプ帳（コレクション）内に抽選応募ボタン表示 → email / name 入力 → DB 保存 → 「市内共通商品券の抽選会に応募しました。」表示 | FR-41 / FR-42 | 一致 |
| スタンプ 15 個で「（15個以上集めたので当選確率2倍です。）」／20 個で「（20個すべて集めたので当選確率3倍です。）」 | FR-42 / FR-43 | 一致 |
| 5 / 10 / 15 / 20 でお菓子配布。会場内のガラポンを実際に回して種類が決まる。アプリ内ガラポン機能なし・ボタンは最大 4 回押下 | FR-37 / FR-38 / FR-40 | 一致 |
| DB に登録されたスタンプ 10 個以上の人の名前とメールアドレスを CSV でダウンロードし業者へ渡す（抽選・当選連絡は業者） | FR-45 / FR-48（`name, email, stamp_count, weight, applied_at`） | 一致 |

**Q-11 を確定（利用者回答 A）**: 応募後に 15 / 20 種へ到達したら、**ページ読み込み時の `check-status` だけでサーバーが `stamp_count` / `tier` / `weight` を自動上方更新**し、画面にも 2 倍 / 3 倍を自動表示する（応募ボタンの再押下は不要・下方更新なし）。

- `requirements.md`: FR-43 改訂（自動昇格）／Q-11 確定／**AC-13 追加**／FR-6・FR-7 のセッションキーを `ar_fingerprint_202611` に明確化／§10 の確定状況を更新
- `design.md`: §5.1 セッションキー／§5.2 checkStatus に `syncVoucherWeight()` による自動昇格と `updated` 返却／§5.3 は副経路と明記／§9（`ar-voucher-applied-202611` は応募済みフラグのみ保存し、倍率文言は常にサーバー応答から描画）／§10（check-status の書き込みをリスクとして明記）／§11 に AC-13
- `tasks.md`: T12 に `syncVoucherWeight()` 追加／T43・T44 を「check-status 応答で描画」に改訂／T45 に AC-13 検証／検証結果表に AC-13

**リポジトリの状態（重要）**: 設計確定前に作成していたドラフト 4 ファイル
（`config/ar202611.php` / `app/Models/Stamp202611VoucherApplication.php` /
`database/migrations/2026_10_28_000001_create_stamp202611_voucher_applications_table.php` /
`app/Http/Controllers/StampRally202611Controller.php`）は**削除した**（`php -l` 検証で Controller が parse error）。
Phase 2 / 3 / 3B の承認後に design.md §3・§5.4 の内容で作り直す。**マイグレーションは未実行**（`stamp202611_voucher_applications` テーブルは未作成）。

## 注意
- 実装時は 202610 含む既存キャンペーンの blade / controller / route / RateLimiter / SW を変更しない（追記のみ）。
- `cg/202610/` アセットを削除または改名すると 202610 と 202611 の両方に影響する。
- スキャンデータは `marker_scans` を既存キャンペーンと共用する。抽選応募（email / name）は 202611 専用の `stamp202611_voucher_applications` にのみ保存し、`prize_exchanges` は使用しない（景品コード廃止）。
- ガラポンの引抽回数はアプリ側（LocalStorage）にしか残らないため、**会場での配布回数確認は運営の目視**に依存する（ブラウザデータ削除で回数はリセットされる）。
