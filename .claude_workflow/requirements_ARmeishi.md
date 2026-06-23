# 要件定義: ARmeishi.blade.php 改良

## 作成日時
2026-06-23

## 対象ファイル
- `resources/views/ARmeishi.blade.php`

## 背景
- 現在の ARmeishi はボールを投げるシステムを含む。ユーザーはピンチ操作で拡大縮小し、ダブルタップでアニメーション切り替えを行いたい。

## 要件
1. ボールを投げるシステムを廃止する。
2. ピンチイン/ピンチアウトで `#cat-model` を拡大縮小できるようにする。
3. マーカー認識時に `anime01` をループ再生する。
4. 画面のダブルタップで `anime02` を 1 回だけ再生し、完了後に `anime01` のループに戻す。

## 成功基準
- `resources/views/ARmeishi.blade.php` の UI にボール投擲処理が残らない。
- 指で 2 本指ピンチ操作でモデルが自然に拡大縮小できる。
- マーカーが検出されたとき `anime01` がループ再生される。
- ダブルタップで `anime02` が 1 回再生され、続けて `anime01` ループに戻る。
- `php -l resources/views/ARmeishi.blade.php` で構文エラーが出ない。
