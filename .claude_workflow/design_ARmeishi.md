# 設計: ARmeishi.blade.php 改良

## 前提
- 既存の `meishi-animation` コンポーネントは `anime01` ループ再生と `anime02` 再生を提供している。
- ポケボール投擲関連コードは廃止対象。

## アプローチ
1. `ARmeishi.blade.php` からポケボール関連の HTML 要素、コンポーネント、イベントリスナー、関数を削除する。
2. `meishi-animation` コンポーネントに以下の機能を追加する:
   - `playIdleAnimation()` で `anime01` をループ再生
   - `stopIdleAnimation()` で `anime01` を停止
   - `playHitAnimation()` で `anime02` を 1 回再生し、終了後に `anime01` に戻す
3. マーカー検出/喪失イベントで `anime01` の再生/停止を制御する。
4. 画面全体のダブルタップで `meishi-animation.playHitAnimation()` を起動する。
5. 2 本指ピンチ操作で `#cat-model` の拡大縮小を行う。 `scale` を一貫して変更し、 `0.5`〜`2.5` 程度の範囲に制限する。

## 実装上の注意点
- 画面タッチとピンチ操作が両立するように、2 本指操作時はダブルタップ判定を無効にする。
- `anime02` 再生中は再度のダブルタップを無視する。
- マーカー検出前にモデルが読み込まれた場合も、 `markerFound` で `anime01` を開始できるようにする。
