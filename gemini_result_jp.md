# FindAkaeiVR プロジェクトガイド

このプロジェクトは、Laravel + A-Frameで構築された**「VR空間でキャラクターを探すゲーム」**です。新しい開発者が30秒で概要を掴み、開発を始められるように要点をまとめました。

---

## 🚀 30秒でわかる！プロジェクト概要

*   **何を作るもの？**
    *   Webブラウザで遊べる3D/VRゲーム。プレイヤーはVR空間に隠れたキャラクターを探します。
*   **コア技術**
    *   **バックエンド:** PHP / Laravel
    *   **フロントエンド:** JavaScript / A-Frame (VR)
    *   **データベース:** SQLite
    *   **ビルドツール:** Vite
*   **主な機能**
    *   ユーザー登録・ログイン機能
    *   3D/VR空間でのゲームプレイ
    *   ゲームのスコア記録・表示

---

## 🛠️ 開発環境のセットアップ (5分で完了)

1.  **リポジトリをクローンしてディレクトリに移動**

2.  **必要なライブラリをインストール**
    ```bash
    composer install
    npm install
    ```

3.  **環境設定ファイルを準備**
    ```bash
    cp .env.example .env
    php artisan key:generate
    ```

4.  **データベースを準備**
    `(database/database.sqlite` は空のファイルとして存在するため、マイグレーションを実行するだけです)
    ```bash
    php artisan migrate
    ```

5.  **開発サーバーを起動**
    *   ターミナルを2つ開いて、それぞれ以下のコマンドを実行します。
    *   **ターミナル1 (フロントエンドのビルド):**
        ```bash
        npm run dev
        ```
    *   **ターミナル2 (バックエンドサーバー):**
        ```bash
        php artisan serve
        ```

6.  ブラウザで `http://127.0.0.1:8000` にアクセスすると、アプリケーションが表示されます。

---

## 🗺️ 主要ファイルと開発フロー

*   **「VR画面を修正したい」**
    *   `resources/views/findakaei.blade.php` を見てください。
    *   A-FrameのHTMLタグ（`<a-scene>`など）でVR空間が定義されています。3Dモデルの配置やカメラの初期位置などを変更できます。

*   **「新しいURL（ページ）を追加したい」**
    *   `routes/web.php` に追記します。
    *   URLと、それを処理するコントローラーのメソッドを紐付けます。

*   **「スコア保存のロジックを変えたい」**
    *   `app/Http/Controllers/ScoreController.php` を見てください。
    *   `store` メソッドにスコアをデータベースに保存する処理が書かれています。

*   **「データベースに新しいテーブルを追加したい」**
    *   `php artisan make:migration create_new_table` コマンドでマイグレーションファイルを作成します。
    *   作成されたファイルを `database/migrations/` で編集し、`php artisan migrate` を実行します。

*   **「3Dモデルや画像を追加・変更したい」**
    *   `public/cg/` (3Dモデル) や `public/img/` (画像) にファイルを配置します。
    *   配置後、`.blade.php` ファイルから参照パスを更新します。