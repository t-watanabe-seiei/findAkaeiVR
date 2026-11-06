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

### 2.4 抽選 UI の参考値行（FR-59）

`#voucher-status` の参考値行（検出種数・同一ブラウザ合算・閾値未満時の注意文）は
**`?test=1` を付けたページだけ**に表示する（`js-prize.blade.php` の
`const VOUCHER_DIAGNOSTICS_202611 = isTestMode();`）。

| 表示 | `/stamp202611`（本番） | `/stamp202611?test=1`（検証） |
|---|---|---|
| この端末の収集: N 種（抽選の基準・この端末 X 種） | 表示 | 表示 |
| 応募期限・応募ボタン（可否 / disabled の文言） | 表示 | 表示 |
| この端末が見つけたマーカー: M 種（抽選には含めません） | **非表示** | 表示 |
| 同一ブラウザの合算: K 種（参考） | **非表示** | 表示 |
| マーカーを M 種見つけていますが、抽選にはボールを当てて収集した種数だけ使えます | **非表示** | 表示 |

- 応募可否・当選倍率・`check-status` の `deviceDetectedCount` / `stampCount`・管理画面 / CSV の参考値列は
  どちらの URL でも同じ（FR-58 の判定は変わらない）。**表示だけ**が変わる
- FR-58 の収集 / 検出を検証で確認するときは必ず `?test=1` 付きで開く（付け忘れた行には表示されない）
- 判定はページ読み込み時の URL のみ。読み込み済みのページに `?test=1` を追記しても反映されない（§2.1）
- 文言は blade 内 JS に残るため DevTools / view-source からは参照できる（表示の秘匿ではない）。
  元に戻すには 3 箇所の `VOUCHER_DIAGNOSTICS_202611 && ` を外すだけ

### 2.5 ガラポン前の確認（FR-62）と応募後のメールアドレス変更（FR-63）の実機手順

どちらも自動テストでは画面挙動まで確認できないため、**`?test=1` を付けた検証用 URL** で目視確認する
（`?test=1` を付けると変更で生じる応募行の fingerprint も `test_` 前置になり本番集計へ混ざらない・FR-54）。

#### ガラポンの確認ダイアログ（FR-62）

1. `/stamp202611?test=1` でボールを 5 種収集し、スタンプ帳のガラポンボタンを押す
2. 「5種ガラポンを引き済みにしますか？」が出る → **「いいえ」**
   - DevTools → Application → Local Storage → `ar-gacha-draws-202611` が `[]`（または変更前）のまま
   - 画面は「ガラポンは引いていません。」
3. 再度押してオーバーレイ（外側）をタップ → 同じく記録されない
4. 再度押して **「はい」** → `ar-gacha-draws-202611` が `[5]` になり「ガラポン会場本部スタッフにこの画面をお見せください」
5. 同じボタンを再押下 → ダイアログは出ず「この段階のガラポンは引き済みです」だけ（FR-60 の案内と同一）
6. 未到達の 10 種ボタン → ダイアログは出ず「10種集めてからガラポンを引けます」
7. 機内モード（オフライン）でも 1〜6 が同じ手順でできること（fetch 不使用）

#### 応募後のメールアドレス変更（FR-63）

前提: `MAIL_MAILER=smtp`（開発で `log` の場合は `storage/logs/laravel.log` の 6 桁コードを使う）。
`AR202611_EMAIL_VERIFICATION=false` の環境ではコードなしで変更できる（その旨が画面に出る）。

1. `/stamp202611?test=1` で 10 種以上収集して応募する（FR-61 のコード確認を通す）
2. 応募後の `#voucher-status` に以下が出ることを確認:
   - 「応募したメールアドレス: `n***@example.com`」（**マスク済み**。完全なアドレスは表示されない）
   - 「登録したメールアドレスを変更する」ボタン
   - 「※11月末頃に抽選し、当選者のみ事務局より御連絡します。」
3. 変更ボタン → 新しいアドレスを入力 → 確認画面（2 押し込み）で送信 → 「送信しました」
4. **コードを入れずに「変更する」を押さない**（押すと `code_required` で「6 桁の確認コードを入力してください」）
5. 誤コード → 「確認コードが違います（残り N 回）」。5 回失敗 → 「確認コードは無効です。もう 1 度送信してください」
6. 正しいコード → 「メールアドレスを変更しました（n***@example.com）」→ 表示のアドレスが変わり、
   倍率（`.voucher-weight-badge`）と収集種数は変わらないことを確認
7. 旧アドレス（= 現登録）を入れる → 「現在登録済みのメールアドレスと同じです」。
   他人の応募済みアドレスを入れる → 「そのメールアドレスは別の応募で使われています」
8. DB 確認（検証用 DB）: `SELECT email, email_code_sent_at, email_verified_at, weight, stamp_count
   FROM stamp202611_voucher_applications WHERE fingerprint = 'test_...'` →
   `email` のみ変わり `weight` / `stamp_count` は同一、`email_verified_at` は更新時刻
9. 変更期限（既定 `2026-11-30 23:59:59`）を過ぎた設定では変更ボタンが消え、
   API を直接叩いても 422 `change_deadline_passed` になることを確認
   （期限は `check-status` の `emailChangeDeadline`。`.env` の `AR202611_EMAIL_CHANGE_DEADLINE` で一時的に短くして test 後に戻す）

### 2.6 応募フォーム開期中の応募ボタン非表示（FR-61・AC-26）の実機手順

応募手続き中に 6 桁の確認コード欄が消える不具合（2026-11-05）への対応確認。

1. `/stamp202611?test=1` で 10 種以上収集してスタンプ帳を開く
2. 緑の「商品券抽選に応募する」を押す → **この時点で緑ボタンが消え、フォームだけが残る**ことを確認
3. アドレスを入力 → 「確認コードを送信」（2 押し込み）→ 6 桁入力ステップへ
4. **画面上部に緑ボタンが現れないこと**、`#voucher-code`（6 桁欄）がそのまま表示されていることを確認
5. スタンプ帳を閉じて開き直す → 6 桁欄と入力済みアドレスが消えないことを確認
6. 機内モード（オフライン）にして 5 秒 → 解除 → 同じく消えないことを確認（`online` 復帰の背景更新）
7. 「やめる」を押す → フォームが閉じ、**緑ボタンが戻る**ことを確認（収集種数が閾値以上なら）
8. 6 桁を入れて「応募する」→ 応募完了表示（`#voucher-applied-info`）へ切り替わり、
   フォームと緑ボタンが出ないことを確認
9. 応募後の変更フローでも同じ: 変更ボタンを押すと変更ボタンが消え、コード入力中に背景更新でフォームが閉じない

### 2.7 テストデータのリセット（FR-65・AC-27）の実機手順

サーバー側を `stamp202611:reset` で消したあと、**端末側（スタンプ・ガラポン・応募状態）も消えていること**の確認。
`AR202611_RESET_EPOCH` を 1 増やしたあとに 1 回だけ消え、2 回目以降は消えないことを確認する。

1. DevTools → Application → Local Storage で `ar-stamp-rally-202611` / `ar-gacha-draws-202611` /
   `ar-voucher-applied-202611` など 202611 系キーと `ar-reset-epoch-202611`（旧世代）を確認する。
   他キャンペーンの `ar-*-202610` キーが混ざっている端末では、それも一緒に確認する
2. サーバー側で `php artisan stamp202611:reset --dry-run` → `--force` を実行し、`.env` の
   `AR202611_RESET_EPOCH` を 1 増やして `config:clear` / `view:clear` / `cache:clear` する（§11）
3. `/stamp202611?test=1` を**通常どおり読み込む**（ハードリロードでなくてよい）→ Console に
   `[AR202611] client state reset -> epoch <新しい値> / keys removed: N` が出ることを確認
4. Local Storage を確認: **202611 系キーは消えている**、`ar-reset-epoch-202611` は新しい値で再作成されている、
   **`ar-*-202610` は残っている**ことを確認
5. Application → IndexedDB で `ARStampRallyDB202611` が消えていること、Cookie `ar_user_id_202611` が
   消えていることを確認。読み込み直後に新しい `ar-user-id-202611`（`uid_…`）が作られることも確認
6. Application → Service Worker → Cache Storage が `ar202611-html-v4` / `ar202611-core-v4` /
   `ar202611-assets-v4` だけになっていること（`ar202611-*-v3` は消えている）を確認
7. **同じ URL をもう 1 回読み込む** → `client state reset` のログが**出ない**こと（冪等）と、
   5 で作られた新しい `ar-user-id-202611` がそのまま残っていることを確認
8. スタンプ 0 種・ガラポン未引・未応募で画面が出ることを確認（`?test=1` なので fingerprint は `test_fp_…`）

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
5. **応募可否・当選倍率は 1 端末（`fingerprint`）単位**（`deviceStampCountFor()` / FR-56）。
   `stampCountFor()` は `session_id` OR `fingerprint` の**合算**で、監査・表示用に残すだけ。
   隠蔽・削除しても、同一 `session_id` を使い続けた検証端末自身の合算種数には残る。
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
| `reset_epoch` | `1` | テストデータリセットの世代番号（`AR202611_RESET_EPOCH`）。DB を消した日に 1 増やすと端末側も消える（FR-65・§2.7 / §11） |

## 9. 自動テスト

`tests/Feature/StampRally202611Test.php`

| テスト | 確認内容 |
|---|---|
| `test_admin_dashboard_and_csv_exclude_test_fingerprints` | 検証行（`test_fp_…`）が `dashboard202611` の `totalScans` / `uniqueScanners` / 応募一覧に出ない → `?show_test=1` で現れる → CSV（`dataset=voucherApplications202611`）に出ない → `&show_test=1` で出る。`?show_test=1&q=…` の検索でも `show_test` が維持され（検索フォームの hidden）、応募一覧のページネーション URL にも `show_test` が残る |
| `test_prize_view_exposes_test_flag_and_prefix` | `/stamp202611` の HTML に `TEST_FP_PARAM` / `TEST_FP_PREFIX`（`"test_"`）と `isTestMode` が出力される |
| `test_voucher_diagnostics_lines_are_gated_to_test_mode` | `/stamp202611` と `/stamp202611?test=1` の HTML に `VOUCHER_DIAGNOSTICS_202611 = isTestMode();` が出力され、参考値行 3 文言（検出種数 / 同一ブラウザ合算 / 閾値未満時の注意文）がすべて `VOUCHER_DIAGNOSTICS_202611` ゲート内侧にある（FR-59・§2.4） |
| `test_voucher_apply_button_is_hidden_while_apply_form_is_open` | `/stamp202611` の HTML に開期中フラグ `_voucherFormOpen202611` と `button.style.display = (showButton && !_voucherFormOpen202611)`、`if (form && applied) form.style.display` が出力され、旧実装 `if (form && !showButton) form.style.display` を含まない（FR-61・AC-26・§2.6） |
| `test_client_reset_epoch_script_is_rendered_before_service_worker` | `/stamp202611` の HTML に世代スクリプトが head の「Service Worker 登録」より前・body の `LocalStorage操作` より前に出力され、`var EPOCH = <config の reset_epoch>`、冪等ガード、選別条件 `k.indexOf('ar-') !== 0 || k.indexOf('202611') === -1`（`ar-*-202610` を消さない）、`indexedDB.deleteDatabase(IDB_NAME)`、Cookie `ar_user_id_202611` 削除、`postMessage({ type: 'CLEAR_CACHES' })` を含む（FR-65・AC-27・§2.7） |
| `test_reset_command_clears_scans_and_voucher_applications` | `stamp202611:reset` は `--force` 無しで exit 1（DB 変化なし）、`--dry-run` は exit 0 で削除 0 件かつ `現在の AR202611_RESET_EPOCH` を表示（§11.2 / §11.4 の確認手順）、`--force` は収集 12 + 検出 3 の `marker_scans` と応募 1 件を 0 件へ戻し、**同じ fingerprint / email で再収集・再応募できる**（FR-65・AC-27） |

§5 の事後整理（前置変更 / 削除）は運用手順であり自動テストにはしていない。実施後は
`/admin/dashboard202611?show_test=1` と既定表示の差分、および CSV の行数で手動確認する。

実行（`RefreshDatabase` が `database/database.sqlite` を消すため `:memory:` を必ず併用）:

```
DB_CONNECTION=sqlite DB_DATABASE=:memory: ./vendor/bin/phpunit --filter=StampRally202611Test
```

クラス全体は **55 tests / 481 assertions**（2026-11-06 時点）。1 端末基準（FR-56 / FR-57）の
テスト 6 件、収集のみで判定する FR-58 のテスト 4 件（検出のみで応募不可／収集・検出の併記／
閾値と倍率／`--recount-device-count` の是正）、参考値行の表示ゲート FR-59 のテスト 1 件の
一覧は `design.md` §10。

（`php artisan test` は非 TTY 環境で "stdout is not a tty" となるため `./vendor/bin/phpunit` を使う）
（ローカル（Windows）で `journal_mode=WAL` が `database is locked` → `database disk image is malformed`
へ至る環境がある。その場合は上の `:memory:` 併用、または `DB_SQLITE_JOURNAL_MODE=DELETE` で実行する）

## 10. 応募倍率の 1 端末基準化（FR-56 / FR-57）を本番 DB へ反映する手順

`device_stamp_count` / `device_tier` / `device_weight`（migration `2026_11_02_000000_…`）と
`stamp202611:backfill-device-weight` を本番へ適用する手順。**抽選確定前に 1 回**実行する。

1. **バックアップ**: `stamp202611_voucher_applications` を退避する（sqlite なら
   `database/database.sqlite` をコピー、MySQL なら `mysqldump … stamp202611_voucher_applications`）
2. **migration 反映**: `php artisan migrate`（未適用の 1 本だけ走る。既存列と既存値は変わらない）
3. **差分確認（DB 更新なし）**:

```
php artisan stamp202611:backfill-device-weight --dry-run
```

   1 応募 1 行で `id=… <email> 合算N種 → 1端末M種 / weight X → Y [grandfathering]`、末尾は
   `dry-run: 対象 N 件 / 更新 M 件（grandfathering G 件）`。`[grandfathering]` は合算基準で
   付与済みの倍率が 1 端末基準より高かった行（**下げない**）を示す。
4. **本実行**: 同じコマンドから `--dry-run` を外すだけ。冪等なので再実行しても差分は出ない
5. **確認**: `/admin/dashboard202611` の「1 端末種数（正本）」列と `weight`、CSV
   （`GET /admin/export?dataset=voucherApplications202611` の `device_stamp_count` /
   `device_weight`）が dry-run 出力と一致すること。業者への抽選は `weight`（= `device_weight`）で行う

- **検証行（`test_` 前置）も backfill 対象**になる（除外オプションはない）。検証行を整理してから
  （§5）実行するか、`?show_test=1` 表示で切り分けて確認する
- 列導入前に応募した行（`device_*` が NULL）は付与済み `weight` を正本として維持する（FR-57）。
  1 端末種数が足りないことを理由に応募を差し戻す運用は行わない
- 集計確認は §5.7 のとおり `php artisan tinker` を使わず、このコマンドと管理画面で行う

### 10.1 収集基準への是正（FR-58・`--recount-device-count`）

`deviceStampCountFor()` は `capture_type='ball_hit'`（ボールを当てて収集した種数）だけ数えるようになった。
**検出（`marker_scan`）込みで水増しされていた `device_stamp_count` を是正する**のが `--recount-device-count` で、
§10 の 2（migration 適用）の後か、既に backfill を済ませた後のいずれかで 1 回実行する。

```
php artisan stamp202611:backfill-device-weight --recount-device-count --dry-run
php artisan stamp202611:backfill-device-weight --recount-device-count
```

- 出力は 1 行 1 応募で `id=… <email> 合算N種 → 1端末収集M種（検出K種） / weight X → Y [recount]`。
  `[recount]` は収集基準で**下げた**行、`[grandfathering]` は付与済みの倍率が収集基準の倍率より高く
  **上げない / 下げない**行を示す。末尾は
  `dry-run / recount-device-count: 対象 N 件 / 更新 M 件（grandfathering G 件・下方更新スキップ S 件）`
  （`--recount-device-count` 時は下方更新スキップ 0 件になる）
- **`weight` / `device_weight` は下げない**（FR-57 の grandfathering を維持）。検出のみで閾値へ到達して
  2 倍・3 倍が付与済みの行も倍率はそのまま残るため、抽選で使い直すかどうかは運営が手動で判断する
- 合算列 `stamp_count` / `tier` は検出込みの値を監査用に保持する（このコマンドは変更しない）
- 既定モード（`--recount-device-count` なし）は上方更新のみで、収集基準で**小さい値へは更新しない**。
  水増し分の是正は必ず `--recount-device-count` を使う
- 冪等: 本実行後に再度 `--recount-device-count --dry-run` を走らせて「更新 0 件」になれば完了
- **コントローラのロジック変更はキャッシュクリアが必須**: `php artisan optimize:clear`
  （`cache:clear` だけでは PHP 側の古いロジックが残り、応募可否が旧基準で返ることがある）
- 非 TTY のシェル（CI・一部 SSH 環境）では `php artisan …` が "stdout is not a tty" で失敗する。
  その場合は §5.7 と同じく一時的スクリプトで Kernel を直接呼ぶ（実行後に削除）:

  ```php
  <?php // storage/app/tmp_recount.php（実行: php storage/app/tmp_recount.php・完了後に削除）
  require __DIR__.'/../vendor/autoload.php';
  $app = require __DIR__.'/../bootstrap/app.php';
  $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
  $kernel->bootstrap();
  foreach ([
      ['optimize:clear', []],
      ['stamp202611:backfill-device-weight', ['--recount-device-count' => true, '--dry-run' => true]],
  ] as [$name, $options]) {
      $buffer = new Symfony\Component\Console\Output\BufferedOutput();
      $exit = $kernel->call($name, $options, $buffer);
      file_put_contents(__DIR__.'/tmp_recount.txt',
          $name.' exit:'.$exit.PHP_EOL.$buffer->fetch().PHP_EOL, FILE_APPEND);
  }
  ```

  差分確認後、`--dry-run` を `false` に変えて本実行し、もう 1 回 `--dry-run` を走らせて「更新 0 件」を確認する

## 11. テストデータのリセット（FR-65・AC-27）

テスト運用で積み上がった収集・検出・抽選応募を初期状態へ戻す手順書。
**サーバー DB を消すだけでは端末は応募済み・ガラポン引き済みのまま**残る（正本は端末の
localStorage / IndexedDB）。逆に**端末だけ消すとサーバーに古い行が残る**。必ず 2 段・この順序で実施する。

### 11.0 全体像：何がどこで消えるのか

| どちらを消すか | 手順 | 手段 |
|---|---|---|
| サーバー側（`marker_scans` / `stamp202611_voucher_applications` / `sessions` / `cache`） | 1 → 2 | artisan コマンドが DB を直接削除 |
| 参加者の端末側（localStorage / IndexedDB / SW キャッシュ / Cookie） | 3 → 4 → 5 | サーバーが世代番号を 1 上げ、**端末が自分で**捨てる |

端末の中身はサーバーから直接消せません。端末が HTML 内の世代番号（`AR202611_RESET_EPOCH`）を
自分で覚えている値と比較し、**不一致のときだけ**消す仕組みです。そのため手順 3（番号）／4（番号を届ける）／
5（古い値を返さない）の 3 つが揃わないと端末は 1 バイトも消えません。

### 11.1 手順 0：バックアップ（推奨）

`stamp202611:reset` は **`marker_scans` を全行削除**します。このテーブルは 202610 以前と共有で
キャンペーン区別の列が無いため、**過去キャンペーン（202603〜202610）のスキャン履歴も一緒に消えます**。

```
cp database/database.sqlite database/backup_before_reset_YYYYMMDD.sqlite   # DB_CONNECTION=sqlite の場合
```

過去分を残すなら **このコマンドを使わず**、`scanned_at >= '2026-10-29 00:00:00'`
（`config('ar202611.collect_start')`・`AR202611_COLLECT_START`）条件の SQL で削除する。

### 11.2 手順 1：`--dry-run`（削除しない・件数確認だけ）

```
php artisan stamp202611:reset --dry-run
```

出力の読み方：

```
削除対象: marker_scans 214 件（2026-10-29 00:00:00 以降 26 件・fingerprint 3 件）/ 抽選応募 2 件
cache: flush する / sessions: 57 件削除
marker_scans は 202610 以前と共有です。過去キャンペーンの履歴も削除されます。
現在の AR202611_RESET_EPOCH = 1          ← 手順 3 の現在値
端末側の localStorage / IndexedDB を消すには、この値を 1 増やして .env を更新し、
php artisan config:clear && php artisan view:clear && php artisan cache:clear を実行してください（FR-65）。
dry-run: 何も削除していません。実行するには --force を付けてください。
```

- 1 件目の数値 = **テーブル全体の総数**（＝過去分も含めて消える実数）。括弧内 = 収集期間以降の 202611 分だけ
- `現在の AR202611_RESET_EPOCH` は**手順 3 の現在値**（dry-run でも表示される）。`.env` に未設定なら `config/ar202611.php` の
  `env('AR202611_RESET_EPOCH', 1)` で `1` になる（ローカル `.env` は 2、`.env.example` は 1）
- `--force` 無しは exit 1 で終了し、削除は走らない（意図した安全装置）
- `SQLSTATE[42S02] ... no such table` が出る場合は本番 DB に 202611 の migration が未適用。
  先に `php artisan migrate --force` が必要（リセット自体は migration を要求しない）
- SSH が非 TTY で `stdout is not a tty` になる場合は §10 と同じ一時的スクリプトで呼ぶ（実行後に削除）

### 11.3 手順 2：`--force`（ここで実際に消える）

```
php artisan stamp202611:reset --force
php artisan stamp202611:reset --force --keep-sessions --keep-cache   # 下表の副作用を残す場合
```

1 トランザクション内で ①`marker_scans` 全行 ②`stamp202611_voucher_applications` 全行
③`sessions` ④SQLite の `sqlite_sequence` 該当行（次回 id が 1 に戻る）⑤`Cache::flush()` の順に実行。

完了出力（同じく末尾に世代番号が再表示される）：

```
削除完了: marker_scans 214 件 / 抽選応募 2 件 / sessions 57 件 / cache flush
現在の AR202611_RESET_EPOCH = 2
```

| 対象 | 既定 | 既定の副作用 | 残すフラグ |
|---|---|---|---|
| `marker_scans` 全行（収集・検出問わず） | 削除 | 過去キャンペーン分も消える（§11.1） | — |
| `stamp202611_voucher_applications` 全行 | 削除 | `fingerprint` / `email` の UNIQUE が解放され再応募可能に | — |
| `sessions` | 削除 | 参加者の `ar_fingerprint_202611` 紐付きが切れる。**ログイン中の `/admin/dashboard202611` はログアウト**（CSV は実行前にダウンロード） | `--keep-sessions` |
| `cache` | `Cache::flush()` | `CACHE_STORE=database` なら `cache` テーブルを truncate。**RateLimiter の 1 時間窓**（分 120 / 時 10）と**未使用のメール確認コード**（`ar202611:voucher-email-code:*`）が無効化 | `--keep-cache` |
| `prize_exchanges` | **触らない** | 202611 では不使用（202609 / 202610 専用） | — |

### 11.4 手順 3：`.env` の `AR202611_RESET_EPOCH` を 1 増やす

判定は厳密一致です（`js-reset.blade.php`）：

```js
var stored = localStorage.getItem('ar-reset-epoch-202611');   // 端末が覚えている世代
if (stored !== null && String(stored) === String(EPOCH)) return;   // 一致なら何もしない（冪等）
```

- 端末は前回 `1` を保存済み。サーバーが `1` のままなら一致 → **何も消えない**
- サーバーが `2` なら不一致 → 消去が走り、消去後に `2` を保存 → 次回以降は一致して再発火しない
- つまり「1 増やす」は**発火スイッチ**。増やさないと手順 4・5 をやっても端末は消えない。
  2 以上飛ばしても動作するが次回の増減が分かりにくくなるので 1 刻み
- `.env` は git 管理外。**サーバー上で直接**編集（FTP でダウンロード→追記→アップロード、または管理パネル）

```
AR202611_RESET_EPOCH=2
```

**反映の確認**（削除せずに確認できる）：手順 1 の `--dry-run` を再実行し、末尾が
`現在の AR202611_RESET_EPOCH = 2` になること。`1` のままなら手順 5 が効いていない。

### 11.5 手順 4：反映する 4 ファイルとそれぞれの役割

| ファイル | 状態 | 役割 |
|---|---|---|
| `config/ar202611.php` | 変更 | `'reset_epoch' => (int) env('AR202611_RESET_EPOCH', 1)`。**`.env` の番号を PHP に通す唯一の配管** |
| `resources/views/ARstampRally202611/head.blade.php` | 変更 | `<head>` 内・SW 登録の直前に `@include('ARstampRally202611.js-reset')` |
| `resources/views/ARstampRally202611/js-reset.blade.php` | **新規** | 消去処理の本体。`<head>` で同期実行されるため `js-init`（`DOMContentLoaded` 内）より先に走り、**オフラインキューが再送される前に捨てられる** |
| `public/sw-stamp202611.js` | `VERSION` を `v3` → `v4` | キャッシュ世代名が `ar202611-*-v3` → `ar202611-*-v4` になり `activate` が旧世代を削除。**オフライン利用者にも新しい HTML / アセットを配るための値上げ** |

- 世代番号は `@json(config('ar202611.reset_epoch'))` が HTML 内インライン JS に埋め込まれて届く。
  `<html data-ar202611-reset-epoch>` は blade ではなく **js-reset が実行時に書き込む**検証用目印
- SW キャッシュは js-reset が直接消さず、稼働中 SW へ `postMessage({type:'CLEAR_CACHES'})` を投げる。
  SW 側は `CACHE_PREFIX = 'ar202611-'` 始まりの**全キャッシュを削除**するため、旧 VERSION の SW でも消える

### 11.6 手順 5：キャッシュクリア（migration は不要）

```
php artisan config:clear    # bootstrap/cache/config.php → .env の新しい番号を通す
php artisan view:clear      # storage/framework/views → head.blade の include を反映
php artisan cache:clear     # アプリキャッシュ
```

一括なら `php artisan optimize:clear`。**OPcache 有効環境（Xserver 含む）ではこれら 3 つだけでは古い
`config/ar202611.php` が残り epoch が 1 のまま**なので、PHP 再起動または `opcache_reset()` を併用
（本番の非 TTY 実行と併せて `tasks.md` T65-2 / T65-4 参照）。

**migration 不要な理由**：このリセットは**削除しかしない**（新テーブル・新列なし、UNIQUE 制約もそのまま）。
`php artisan migrate` を走らせても差分は出ない。
※ `tasks.md` で `[ ]` の `2026_11_02_000000`（device_weight 列）／`2026_11_04_000000`（email 認証列）が
本番未適用なら、それは §10 の**別手順**として別途判断。リセットの成否とは無関係。

### 11.7 順序の理由（逆順・省略で何が起きるか）

```
①dry-run → ②force → ③epoch++ → ④upload → ⑤clear
   確認      サーバー   番号      番号を     古い値を
           を空に    上げる    端末へ届かせる 返さない
```

| 誤り | 結果 |
|---|---|
| epoch を先に上げる | 端末だけ空 / サーバーに旧行が残る → 「帳簿は空なのに収集 12 種」の不整合。応募済みフラグも消えるため**同じメールで再応募可能**（二重応募） |
| upload 前に epoch だけ変更 | `config()` が 1 を返し不一致にならない → 端末は消えない |
| clear を省略 | HTML に出てくる epoch が 1 のまま → 端末は消えない |
| ②〜④ の間が空く | 削除済み DB へ**オフラインキュー（`ar-scan-queue-202611`）の再送で検証行が復活**しうる → ②〜⑤ を 10 分程度で連続実行。可能なら検証用オフライン端末を先に 1 回 online で開かせてキューを空にしてから削除 |

### 11.8 端末側で消えるもの・消えないもの

**消える**（次回 online 読み込み時）
- localStorage: `ar-` で始まり `202611` を含むキーを自動列挙して削除
  （`ar-stamp-rally-202611` / `ar-captured-animals-202611` / `ar-gallery-selection-202611` /
  `ar-scan-queue-202611` / `ar-voucher-applied-202611` / `ar-gacha-draws-202611` /
  `ar-camera-reload-202611` / `marker-scan-cache-202611-*`）。将来追加されるキーも自動的に吸収
- IndexedDB: `ARStampRallyDB202611` を `deleteDatabase`（`scans` / `stamps` / `userId` store ごと）
- Cookie: `ar_user_id_202611`
- SW キャッシュ: `ar202611-*`（VERSION 全世代）

**消えない**：`ar-*-202610` など他キャンペーンのキー、Service Worker 自体（unregister しない）、
`prize_exchanges` / `users` などの他テーブル。

**注意：`ar-user-id-202611`（userId）も消え、fingerprint は新しい値で再生成される**（完全な新規端末扱い）。
検証行を `test_` 前置で区別している場合、その検証 fingerprint は変わる（`?show_test=1` での切り分け運用は有効）。
サーバー側は全削除しているため整合は取れるが、「サーバーだけ消して端末は残す」部分実行とは相性が悪い。

**离线端末が消えない理由**：リセットコードは HTML に埋め込まれており、オフラインでは SW のキャッシュ済み
**旧 HTML（旧 epoch）**が返るため不一致を検出できない。navigation は network-first なので
**online 復帰後の最初の読み込みで消える**。完全に通信しない端末は原理的に消せないため、会場スタッフの端末確認か
応募期限（2026-11-16 23:59）前に 1 回 online 化してもらう運用でカバーする。

### 11.9 完了確認

1. `php artisan stamp202611:reset --dry-run` を再実行 → `現在の AR202611_RESET_EPOCH = 2` / `0 件`
2. `/admin/dashboard202611`（sessions を消したため再ログイン要）の総スキャン数・応募数が 0（`?show_test=1` でも 0）
3. CSV `voucherApplications202611` の行数が 0
4. 検証端末の Console に `[AR202611] client state reset -> epoch <新しい値>` が **1 回だけ**出ること（§2.7 の 1〜8）
5. `<html … data-ar202611-reset-epoch="2">` であること。`ar-reset-epoch-202611` は `2` で再作成されており、
   読み込み直後に新しい `ar-user-id-202611`（`uid_…`）が作られること（§2.7 の 5）
6. 同じ端末で再収集 → 再応募できること（fingerprint / email の UNIQUE で弾かれない）
7. 端末実機は `?_v=2` 付き URL で確認（README の「キャッシュで 0 件に見える」落とし穴対策）
