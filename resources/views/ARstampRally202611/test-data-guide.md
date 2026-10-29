# 検証（テスト）データの扱いガイド — ARstampRally202611

> 作成: 2026-10-29 / 改訂: 2026-10-29（§5 付け忘れ時の事後整理・§6 検証ログを追加）
> 対象要件: `requirements.md` FR-54・AC-17 / 設計: `design.md` §3.4
> 目的: 複数端末で検証した計測を、管理ダッシュボードの集計と業者提出用 CSV から自動的に除外する。

## 1. 仕組み（1 行で）

`?test=1` を付けた URL で参加した端末だけ **fingerprint に `test_` を前置**し、
サーバー側は fingerprint が `test_` で始まる行を集計・CSV から除外する
（`?show_test=1` を付けたリクエストだけ表示）。

- DB スキーマは変更しない（`marker_scans` / `stamp202611_voucher_applications` ともに列追加なし）
- 除外は `AdminController::applyTestFilter()` 1 箇所（`fingerprint NOT LIKE 'test_%'`）に集約
- 本番計測の fingerprint は常に `fp_` 始まり（`js-prize.blade.php` の `generateFingerprint()`）のため混同しない
- 検証モードの値は blade（`js-prize.blade.php`）が `TEST_FP_PARAM` / `TEST_FP_PREFIX` 定数として注入し、
  `isTestMode()` が**そのページの URL** から判定する（クエリ名・前置字は `config/ar202611.php` からのみ読む）

## 2. 使い方

### 2.1 検証する（テスト計測にする）

| 対象 | URL |
|---|---|
| 参加ページ | `/stamp202611?test=1` |

- 検証用端末では **毎回 `?test=1` を含んだ URL** で開く（QR・ブックマークも `?test=1` 付きで保存）。
  前置字は「そのページを読み込んだときの URL」で決まるため、`?test=1` なしで開いた回の送信は本番計測になる
- Service Worker は navigation を network-first で取得するため `?test=1` はサーバーへ届く
- **fingerprint は保存されない**。保存されるのは userId（LocalStorage `ar-user-id-202611` /
  Cookie `ar_user_id_202611`）で、fingerprint は送信のたび userId + 端末属性から生成される。
  オフラインキューはスキャン時の fingerprint を保持するため、`?test=1` 付きページで集めた分は再送時も `test_` のまま
- オフラインページ（`/stamp202611/offline`）は fingerprint を生成しないため、`?test=1` を付けても効果がない
- 検証応募には専用の email（例 `test1@example.com`）を使う（§7 参照）

**付け忘れが実際に起きるパターン**

| ケース | 結果 | 根拠 |
|---|---|---|
| オフラインページの「AR を続ける / やり直す / キャッシュ削除」で戻る | `?test=1` を引き継がず**本番計測**になる | `offline.blade.php` のリンクが `url('/stamp202611')`（クエリを復元しない） |
| 読み込み済みのページでアドレスバーに `?test=1` を追記しただけ | 無効（`location.search` は navigation でしか変わらない） | `js-prize.blade.php` の `isTestMode()` |
| QR・ブックマーク・共有リンクが `?test=1` なし | 本番計測 | — |
| カメラ拒否・タイムアウト後の自動リロード（iOS 対策） | **保持される**（`location.reload()` はクエリを維持） | `head.blade.php` の camera watchdog |
| オフライン中に集めた分の再送 | **保持される**（キューがスキャン時の fingerprint を保存） | `js-prize.blade.php` の `ar-scan-queue-202611` |

付け忘れた場合は §5 で事後整理する。

### 2.2 管理ダッシュボード

| 表示 | URL |
|---|---|
| 通常（検証データを除外） | `/admin/dashboard202611` |
| 検証データを含めて確認 | `/admin/dashboard202611?show_test=1` |

画面上部に切替リンク（「検証データを表示」/「検証データを非表示」）があり、直下に現在の状態を
「検証データは 表示しています／除外しています（現在の接頭辞: `test_`）」として表示する。
検索フォームは hidden で `show_test` を引き継ぎ、ページネーションも `show_test` を維持する
（`?voucher_page=2&show_test=1`）。

### 2.3 業者提出用 CSV

| 用途 | URL |
|---|---|
| **本番提出用**（検証分を除外・既定） | `/admin/export?dataset=voucherApplications202611` |
| 検証分も含めて確認 | `/admin/export?dataset=voucherApplications202611&show_test=1` |
| 期間で絞る | 上記に `&start=2026-11-01%2000:00:00&end=2026-11-16%2023:59:59`（`applied_at` に適用）。ダッシュボードのボタンは収集期間を自動で付与する |

`recentScans` / `dailyScans` / `markerStats` の CSV でも `show_test` は同じく効く。
**抽選実施直前は、`show_test` を付けない URL（＝除外側）で CSV を提出したかを確認する。**

## 3. 除外が効いている箇所

| 画面・データ | 実装箇所 |
|---|---|
| 総スキャン数・個別ユーザー数 | `AdminController::dashboard202611()` |
| 動物別（マーカー別）統計 | 同上（マーカー別ループ内） |
| 最近のスキャン履歴 | 同上 |
| 日別統計・日別個別ユーザー数 | 同上 |
| 抽選応募の総数・倍率別内訳・応募一覧 | 同上 |
| CSV `voucherApplications202611` | `AdminController::exportCsv()` |
| CSV `recentScans` / `dailyScans` / `markerStats` | 同上 |

除外に使う列は `fingerprint` のみ。`marker_scans` は 202610 他キャンペーンと共有だが、
他キャンペーンの fingerprint は `test_` 始まりにならないため既存集計には影響しない。

## 4. 検証用端末の準備・リセット手順

1. `/stamp202611?test=1` を開く
2. DevTools → Console で `isTestMode()` が `true`、`await generateFingerprint()` の値が `test_fp_…`
   であることを確認する（`ar-user-id-202611` は UUID のまま。fingerprint は保存されない）
3. 既に本番計測してしまった端末を検証用へ切り替える（本番行は残るため、必要なら §5 で整理）:
   - Local Storage の `ar-user-id-202611` / `ar-stamp-rally-202611` / `ar-captured-animals-202611` /
     `ar-gacha-draws-202611` / `ar-voucher-applied-202611` / `ar-scan-queue-202611` /
     `marker-scan-cache-202611-*` を削除
   - Cookie `ar_user_id_202611` を削除
   - `/stamp202611?test=1` を開き直す（ブラウザの「サイトのデータを削除」でも可）

検証で入れたデータを本番 DB から消す場合（集計には出ないが、抽選前に整理しておくと安全）:

```
php artisan tinker
>>> App\Models\Stamp202611VoucherApplication::where('fingerprint', 'like', 'test_%')->count();  // 確認
>>> App\Models\Stamp202611VoucherApplication::where('fingerprint', 'like', 'test_%')->delete(); // 削除
>>> App\Models\MarkerScan::where('fingerprint', 'like', 'test_%')->delete();                    // 任意
```

`test_` 始まりの行だけが対象（実参加者には影響しない）。`?test=1` を付け忘れた行はここに現れないため §5 で扱う。

## 5. `?test=1` 無しで計測してしまった場合の事後整理

付けたかどうかはサーバー側では判別できない。ただし**付け忘れた行だけを特定して、検証データ扱い（隠蔽）または削除できる**。
`fingerprint` は `userId + 端末属性` からのハッシュのみで、前置字の有無で末尾は変わらない。

### 5.1 対象 fingerprint の特定（推奨）

1. 付け忘れた端末で `/stamp202611` を開く（`?test=1` なし）
2. DevTools → Console で `await generateFingerprint()` を実行し、値を控える（例 `fp_1a2b3c`）
3. 同じ端末で `/stamp202611?test=1` を開き直し、再度 `await generateFingerprint()` を実行する
   （例 `test_fp_1a2b3c`）
4. 前置字を除いた末尾（`1a2b3c`）が一致すれば、`fp_1a2b3c` が付け忘れ分である

前提: 手順 1 と 3 で **同じ userId を使っている**こと。LocalStorage `ar-user-id-202611` と
Cookie `ar_user_id_202611` を消すと userId が変わり、fingerprint も別になる（この技は使えない）。

### 5.2 方法 A: `test_` 前置へ変更して隠す（推奨・非破壊）

集計・CSV は `applyTestFilter()`（`fingerprint NOT LIKE 'test_%'`）1 箇所に集約されているため、
前置を付けるだけで dashboard・CSV のすべてから即除外される。行は残るので検証証跡もロールバックも保てる。

```
php artisan tinker
>>> // 確認（件数）
>>> App\Models\MarkerScan::where('fingerprint', 'fp_1a2b3c')->count();
>>> App\Models\Stamp202611VoucherApplication::where('fingerprint', 'fp_1a2b3c')->count();
>>> // 変更（applyTestFilter の対象にする）
>>> App\Models\MarkerScan::where('fingerprint', 'fp_1a2b3c')->update(['fingerprint' => 'test_fp_1a2b3c']);
>>> App\Models\Stamp202611VoucherApplication::where('fingerprint', 'fp_1a2b3c')->update(['fingerprint' => 'test_fp_1a2b3c']);
```

ロールバック（本番計測へ戻す）は逆方向の `update()` で行う。

- `marker_scans.fingerprint` には UNIQUE 制約がないため変更は安全
- `stamp202611_voucher_applications.fingerprint` は UNIQUE(64)。変更先が既存の検証行と衝突する場合は
  `test_orphan_fp_1a2b3c` など別の前置名を使う（`test_` を含むため除外されたまま）
- 前置字は `config/ar202611.php` の `test_prefix`（既定 `test_`）に一致する必要がある

### 5.3 方法 B: 完全削除

応募テーブルは `email` UNIQUE を解放するため **応募 → スキャンの順**で削除する。

```
php artisan tinker
>>> App\Models\Stamp202611VoucherApplication::where('fingerprint', 'fp_1a2b3c')->count();  // 確認
>>> App\Models\Stamp202611VoucherApplication::where('fingerprint', 'fp_1a2b3c')->delete(); // 応募
>>> App\Models\MarkerScan::where('fingerprint', 'fp_1a2b3c')->delete();                    // スキャン
```

**条件は `fingerprint` の完全一致のみ。** `LIKE 'fp_%'` などのバッチ削除は実参加者の行を消すため禁止。

### 5.4 方法 C: 端末を特定できない場合

`fingerprint` を取得できないときは、期間と補助情報で候補を絞ってから目視確認する。

```
php artisan tinker
>>> App\Models\MarkerScan::whereBetween('scanned_at', ['2026-11-01 10:00:00', '2026-11-01 11:00:00'])
>>>     ->where('fingerprint', 'not like', 'test_%')
>>>     ->get(['id', 'marker_id', 'fingerprint', 'user_agent', 'ip_address', 'scanned_at']);
```

同一機種・同一時間帯の実参加者が混ざるため、**必ず fingerprint 単位で確認してから** 5.2 / 5.3 を適用する。

### 5.5 方法 D: 応募だけが残った場合

応募は email で特定する（検証専用の email を使っていれば安全）。

```
php artisan tinker
>>> App\Models\Stamp202611VoucherApplication::where('email', 'test1@example.com')->count();
>>> App\Models\Stamp202611VoucherApplication::where('email', 'test1@example.com')->delete();
```

### 5.6 実施前のチェック

1. **バックアップ**: sqlite なら `database/database.sqlite` をコピー、MySQL なら `mysqldump`
2. **`->count()` で preview**（`->delete()` / `->update()` は件数確認後）
3. 可能なら `DB::transaction(function () { … })` でまとめる
4. 実施後に `/admin/dashboard202611?show_test=1` で「隠れた（または消えた）」ことを確認し、
   既定表示（`show_test` なし）でも総スキャン数・応募数が期待どおりか確認する
5. **`stampCountFor()` は `session_id` OR `fingerprint` で種数を数える**（`StampRally202611Controller`）。
   隠蔽・削除しても、同一 `session_id` を使い続けた検証端末自身の種数には残る。
   検証端末を本番へ回す場合は §4 の LocalStorage / Cookie リセットを先に行う

### 5.7 実行環境の注意

`php artisan tinker` / `php artisan tinker --execute=…` は非 TTY 環境（CI・一部シェル）で
"stdout is not a tty" により失敗する。対話 terminal で実行するか、`bootstrap/app.php` を読む
一時スクリプト（実行後に削除）を使う。

## 6. 検証ログの記録

付け忘れの検知はサーバー側ではできないため、運用メモで補う。検証終了時に下表を残す。

| 日時 | 機種 / OS | 開いた URL | fingerprint | 検証 email | 結果 |
|---|---|---|---|---|---|
| 2026-11-01 10:12 | iPhone 15 / iOS 18 | `/stamp202611?test=1` | `test_fp_1a2b3c` | `test1@example.com` | OK |
| 2026-11-01 10:40 | Pixel 8 / Android 15 | `/stamp202611`（付け忘れ） | `fp_9z8y7x` | — | §5.2 で `test_fp_9z8y7x` へ変更 |

- 「開いた URL」に `?test=1` が含まれていない行が、そのまま本番計測になっている候補
- 検証用端末の台数分だけ行を残し、抽選前に §5 で処理したかを確認する

## 7. 注意・限界

- **fingerprint はクライアント側で生成**する。目的は「検証データの誤計上を防ぐ」ことであり、
  意図的な改ざん（`test_` を外して送信する行為）を防ぐ仕組みではない
- `?test=1` を付け忘れた検証は本番計測として集計される（付けたかどうかの判別はできない）
  → 付け忘れ分の特定と整理は §5
- 応募の重複判定（1 email 1 回）は **email ベース**。検証で使った email を本番応募に流用すると
  `already_applied`（422）になる → 検証は専用の email・name を使う
- 検証データも UNIQUE 制約（email / fingerprint）の対象。同一検証端末での重複応募は本番と同じく弾かれる
- 202610 以前のキャンペーンには本機構はない（`?test=1` を付けても影響しない）
- 前置字 `test_prefix` を変更すると、それまでに保存された検証行は除外対象でなくなる

## 8. 設定（`config/ar202611.php` / `.env`）

| キー | 既定値 | 内容 |
|---|---|---|
| `test_param` | `test` | 検証モードを起動するクエリ名（`?test=1`） |
| `test_prefix` | `test_` | fingerprint に付ける前置字 |

## 9. 自動テスト

`tests/Feature/StampRally202611Test.php`

| テスト | 確認内容 |
|---|---|
| `test_admin_dashboard_and_csv_exclude_test_fingerprints` | 検証行（`test_fp_…`）が `dashboard202611` の `totalScans` / `uniqueScanners` / 応募一覧に出ない → `?show_test=1` で現れる → CSV（`dataset=voucherApplications202611`）に出ない → `&show_test=1` で出る。`?show_test=1&q=…` の検索でも `show_test` が維持され（検索フォームの hidden）、応募一覧のページネーション URL にも `show_test` が残る |
| `test_prize_view_exposes_test_flag_and_prefix` | `/stamp202611` の HTML に `TEST_FP_PARAM` / `TEST_FP_PREFIX`（`"test_"`）と `isTestMode` が出力される |

§5 の事後整理（前置変更 / 削除）は運用手順であり自動テストにはしていない。実施後は
`/admin/dashboard202611?show_test=1` と既定表示の差分、および CSV の行数で手動確認する。

実行（`RefreshDatabase` が `database/database.sqlite` を消すため `:memory:` を必ず併用）:

```
DB_CONNECTION=sqlite DB_DATABASE=:memory: ./vendor/bin/phpunit --filter=StampRally202611Test
```

（`php artisan test` は非 TTY 環境で "stdout is not a tty" となるため `./vendor/bin/phpunit` を使う）
