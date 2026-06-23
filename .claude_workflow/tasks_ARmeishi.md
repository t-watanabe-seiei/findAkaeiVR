# タスク: ARmeishi.blade.php 改良

## タスク一覧
1. `resources/views/ARmeishi.blade.php` からポケボール投擲に関わる HTML・CSS・JavaScript を削除する。
2. `meishi-animation` コンポーネントに `playIdleAnimation()` / `stopIdleAnimation()` を追加し、 `anime01` の再生制御を行う。
3. マーカー検出イベントで `playIdleAnimation()` を開始し、喪失イベントで停止する。
4. 画面全体のダブルタップ検出と `anime02` 再生制御を追加する。
5. 2 本指ピンチでモデル拡大縮小を実装する。
6. `php -l resources/views/ARmeishi.blade.php` で構文エラーがないことを確認する。
