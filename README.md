## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

# Creation history

### 001 laravel 11 template fork

### 002 php artisan migrate (create [Table] cache [Table] jobs [Table] users)

### 003 php light admin introduce (1.9.9-dev)

### 004 show hidden files (Display switching)

### 005 php artisan key:generate (create APP_KEY)
### 006 php artisan config:cache (env cache clear) 
### 007 edit .env

    DB_CONNECTION=sqlite
    
### 008 Livewire install ( version 3.5 )

    composer require livewire/livewire
    
### 009 git　setup

    git init

### 010  モデルとマイグレーションファイルの生成。

    php artisan make:model Score -m

### 011 API用コントローラーを作成

    php artisan make:controller ScoreController --api

### 012  Artisanコマンドを使用してAPIルーティングを有効にする（routes/api.phpがないため） 
    
    php artisan install:api

### 013 ルーティングの設定のため routes/api.php に以下を追加。

    Route::resource('/Scores', App\Http\Controllers\ScoreController::class);

### 014 api/books/* が設定されたらOK。

    php artisan route:list

### 015 Scores マイグレーションファイルを編集してカラムの追加。

    Schema::create('scores', function (Blueprint $table) {
        $table->id();
        $table->bigInteger('userid');
        $table->double('time', 8, 3);
        $table->timestamps();
    });

### 016 php artisan migrate (create [Table] scores)

### phpLiteAdmin にてダミーデータを挿入 Score

### Api get 動作確認

    一覧取得
    curl -X GET http://localhost:8000/api/Scores

    新規登録
    curl -X POST http://localhost:8000/api/Scores  -d 'userid=20230001&time=11.99'

### ScoreController　の  index , store , show , update , destroy を修正

    public function index()
    {
        return Score::all();
    }
    
    public function store(Request $request)
    {
        Score::create($request->all());
    }
    
    public function show(string $userid)
    {
        return Score::where('userid', $userid)->get();
    }
    
    
    public function update(Request $request, string $id)
    {
        Score::where('id', $id)->update([
            'userid' => $request->userid,
            'time' => $request->time
        ]);
    }

    public function destroy(string $id)
    {
        Score:where('id', $id)->delete();
    }
    
### バリデーション用にリクエストクラスを作成。

    php artisan make:request StoreScore

### app\Http\Requests\StoreScore.php ができるのでルールを設定。
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return [
              'userid' => 'required|max_digits:8',
              'time' => 'required|decimal:2,3',
        ];
    }

### Api 動作確認

一覧取得
curl -X GET http://localhost:8000/api/Scores

新規登録
curl -X POST http://localhost:8000/api/Scores  -d 'userid=20230001&time=11.99'

更新（２番目のデータ）
curl -X PUT http://localhost:8000/api/Scores/2  -d 'userid=20240001&time=59.999'

参照（userid=20240001のデータ）
curl -X GET http://localhost:8000/api/Scores/20230001

削除（２番目のデータ）
curl -X DELETE http://localhost:8000/api/Scores/2




# この時点で、https://replit.com/　の　Development Time（600分）　がなくなってしまったので、GitHub　から、Local に Pull
Localは php8.1.26 だったため、 windows.php.net/download から、 
php8.2.23-nts-win32-vs16-x64.zipをダウンロード & php.iniを編集+環境変数の設定(IISの設定は未設定)

    composer install
    php artisan key:generate
    touch database/database.sqlite
    php artisan migrate
    phpliteadmin.php の pathの設定
    
    php artisan serve --host 0.0.0.0 --port 8000

# sourceTree から、githubへ
# xserverへログインして、Gitクローン
    git clone git@github.com:t-watanabe-seiei/findAkaeiVR.git
#
    git fetch
    git pull

# php のバージョンをアップ　php8.2へ

# 使いたいPHPバージョンの存在確認＆パスを調べる
whereis php


## １．ホームディレクトリに「bin」フォルダを作成
mkdir $HOME/bin
## ２．シンボリックリンクを作成する　※今回はPHP8.2を指定しました。
シンボリックリンクの新規作成
ln -s /usr/bin/php8.2 $HOME/bin/php
シンボリックリンクの向き先を変える
ln -nfs /usr/bin/php8.2 $HOME/bin/php
## ３.bashrcにパスを通す記述を追記
vi ~/.bashrc
最終行に以下を追加して保存
export PATH=$HOME/bin:$PATH
## ４．変更内容を反映させる
source ~/.bashrc
## ５．反映されているかを確認
php -v


# composer パッケージのインストール
    composer install
# .env ファイルの存在確認　なければ作成orReName
    mv .env.example .env
# APP_KEY の更新
    php artisan key:generate
# .env ファイルの一部修正

# 公開ディレクトリの設定 ※2024oc.seiei.onlineで以下のコマンド入力
　ln -s /home/seiei9/seiei.online/public_html/2024oc.seiei.online/findAkaeiVR/public find

# Node.jsのバージョンをアップグレード ※Node.jsのバージョンを18.x以上にアップデート

### nodebrewをインストールされたか確認
    nodebrew

## nodebrewでインストール可能なnodeのバージョンを確認
     nodebrew ls-remote

## nodebrewで使いたいバージョンのnodeをインストール
    nodebrew install-binary v20.17.0
    ※エックスサーバーでは、node16 までしか対応していない・・・

## 他にも最新の安定版をインストールしたかったstableとする。
    nodebrew install-binary stable

## nodebrewでインストールされたnodeのバージョンを確認
    nodebrew ls

## 使いたいnodeのバージョンをインストールしたバージョンのリストから選ぶ。
    nodebrew use v22.8.0

### ※Windowsでは、 https://nodejs.org/en/ から、windows版の　Download node.js LTS をダウンロードしてそのままインストールすると、Node.js がバージョンアップされる（node-v20.17.0-x64.msi）

# 
    npm install
    npm audit fix --force

    https://prog-8.com/docs/nodejs-env-win


    

    touch database/database.sqlite
    php artisan migrate
    phpliteadmin.php の pathの設定

    git pull git@github.com:t-watanabe-seiei/vr.git main
    git pull git@github.com:t-watanabe-seiei/findAkaeiVR.git main







## やっぱり　node.js を version 16.20.2 に戻す

## laramix に戻す
    npm install --save-dev laravel-mix
## package.jsonファイルに、”laravel-mix”が追加になります。
    {
        "devDependencies": {
            "laravel-mix": "^6.0.49",
        }
    }
## プロジェクト直下に、webpack.mix.jsファイルを作成し、以下の内容にします。
    const mix = require('laravel-mix');

    /*
    |--------------------------------------------------------------------------
    | Mix Asset Management
    |--------------------------------------------------------------------------
    |
    | Mix provides a clean, fluent API for defining some Webpack build steps
    | for your Laravel applications. By default, we are compiling the CSS
    | file for the application as well as bundling up all the JS files.
    |
    */

    mix.js('resources/js/app.js', 'public/js')
        .postCss('resources/css/app.css', 'public/css', [
            //
        ]);
## package.jsonファイルを開き、“scripts”部分のviteの記述を削除し、以下のようにMixを追加します。
{
    "scripts": {
        "dev": "npm run development",
        "development": "mix",
        "watch": "mix watch",
        "watch-poll": "mix watch -- --watch-options-poll=1000",
        "hot": "mix watch --hot",
        "prod": "npm run production",
        "production": "mix --production"
    },
}
## .env を開き、以下のViteに関する記述を削除します。
    VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
    VITE_PUSHER_HOST="${PUSHER_HOST}"
    VITE_PUSHER_PORT="${PUSHER_PORT}"
    VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
    VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
## .evv にMixに関する記述を追加します。
    MIX_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
    MIX_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"

## webpack.mix.js が ES モジュールとして扱われており、require() 関数で ES モジュールを読み込もうとしているために発生しています。
    解決方法
    webpack.mix.js を webpack.mix.cjs に名前を変更します。

## npm run dev コマンドでエラー巣が起こる場合、ファイルの拡張子を追加することで、webpack が正しくモジュールを解決できるようになります。　C:\MyApp\findAkaeiVR\resources\js\app.js の中身を以下に変更
    import './bootstrap.js';

# もっかい　sourceTree から、githubへ
# xserverへログインして、Gitクローン
    git clone git@github.com:t-watanabe-seiei/findAkaeiVR.git
#
    git fetch
    git pull

# composer パッケージのインストール
    composer install
# .env ファイルのReName
    mv .env.example .env
# APP_KEY の更新
    php artisan key:generate
# .env ファイルの一部修正

## npm install
## npm audit ※
## touch database/database.sqlite
## php artisan migrate
## phpliteadmin.php の pathの設定
    /home/seiei9/seiei.online/public_html/2024oc.seiei.online/findAkaeiVR/database/database.sqlite


curl -X POST http://2024oc.seiei.online/find/api/Scores  -d 'userid=20230001&time=11.99'
curl -X POST http://2024oc.seiei.online/find/api/Scores  -d 'userid=20230002&time=12.03'
curl -X POST http://2024oc.seiei.online/find/api/Scores  -d 'userid=20230003&time=10.87'


### fetch to axios で、get post put （※axios CDN import require　->  use fetch）

    //fetch de get  ※scores からデータ取得
    fetch('http://localhost:8000/api/Scores')            
    .then((response) => response.json())
    .then((datas) => { 
        // console.log(datas);
        // console.log(datas[0].userid);
        datas.forEach(data => {
            console.log(data.userid);
        });
    });

    //fetch de get  ※error 処理付
    fetch('http://localhost:8000/api/Scores')
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(datas => {
        console.log('fetch de get  ※error 処理付');
        datas.forEach(data => {
            console.log(data.userid);
        });
    })
    .catch(error => {
        console.error('There was a problem with the fetch operation:', error);
    });









    

    //fetch de POST
    fetch('http://localhost:8000/api/Scores', {  
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({userid: '20250001',time: '12.34',}
        )
    });

    
    //fetch de PUT
    fetch('http://localhost:8000/api/Scores/1', {  
        method: "PUT",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({userid: '20200001',time: '9.20',}
        )
    });

    //axios de get 既に JSON 形式に変換されている
    axios.get("http://localhost:8000/api/Scores")
    .then(response => {
        console.log('axios de get');

        let datas = response.data;
        datas.forEach(data => {
            console.log(data.time);
        });

    });

    //axios de post
    const dataToSend = {
        userid: '20240003',
        time: '10.53',
    };
    axios.post('http://localhost:8000/api/Scores', dataToSend)
    .then(response => {
        // リクエスト成功時の処理
        console.log('axios de post');
        console.log('Data created:', response.data);
    })
    .catch(error => {
        // リクエスト失敗時の処理
        console.error('Error creating data:', error);
    });


    //axios de put
    const dataToSend2 = {
        userid: '20230009',
        time: '9.99',
    };
    axios.put('http://localhost:8000/api/Scores/5', dataToSend2)
    .then(response => {
        // リクエスト成功時の処理
        console.log('axios de put');
        console.log('Data updated:', response.data);
    })
    .catch(error => {
        // リクエスト失敗時の処理
        console.error('Error creating data:', error);
                            });
## fetch に 環境変数　MIX_ASSET_URL を追加　　
    fetch("{{ env('MIX_ASSET_URL') }}" + '/api/Scores')            
    .then((response) => response.json())
    .then((datas) => { 
        datas.forEach(data => {
            console.log(data.userid);
        });
    });

    fetch("{{ env('MIX_ASSET_URL') }}" + '/api/Scores', {  
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({userid: '20260001',time: '23.45',}
        )
    });

    fetch("{{ env('MIX_ASSET_URL') }}" + '/api/Scores/1', {  
        method: "PUT",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({userid: '20180001',time: '88.88',}
        )
    });


## ScoreControllerでの戻り値の修正
    index（Top5をGETで返す）
    show(userID) 自己の記録をすべて返す

## moveTest.blade作成　
    public/js/aframe-particle-system-component.min.js を利用して、particle


## 360video　を再生するときは、a-videosphere
        <a-assets>
            <video id="video" src="{{ asset('cg/R0010008_st_001.MP4') }}"
        </a-assets>

        <a-videosphere id="videosphere" src='#video' visible="false"></a-videosphere>

# UbuntuにLaravelをインストール
    Ubuntu
    php
    git
    node.js
    npm
    ssh
    git clone (/var/www/html でユーザ権限で実行)
    composer install
    mv .env.example .env
    php artisan key:generate
    ln -s findAkaeiVR/public app
    .env の中の MIX_ASSET_URL="/app"　へ修正
    npm install
    npm audit fix
    touch daatbase/database.sqlite
    phpliteadmin.php
    chmod -R 777 storage
    sudo chmod 664 /var/www/html/findAkaeiVR/database/database.sqlite
    sudo chown www-data:www-data /var/www/html/findAkaeiVR/database/database.sqlite
    sudo chmod 775 /var/www/html/findAkaeiVR/database
    sudo chown -R www-data:www-data /var/www/html/findAkaeiVR/database
    sudo service apache2 restart
    (sudo apt-get install php-curl)　※不要かも
    /etc/apache2/apache2.conf の中の "var/www/html" no  AllowOverride None →　AllowOverride All に変更

# Laravel/UI インストール
    composer require laravel/ui
    php artisan ui bootstrap --auth
    npm install
    npm audit fix
    npm run dev
    welcome -> login でエラー   views/layouts/app.blade.php を書き換え
        @vite(['resources/sass/app.scss', 'resources/js/app.js'])　←　削除

        以下を追記
        <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
        <link 
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
            rel="stylesheet" 
            integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM"
            crossorigin="anonymous"
        />
        <link rel="stylesheet" href="{{ asset('css/app.css') }}">