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