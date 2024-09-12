<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>VR contents</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="vr content">
<style>
@charset "utf-8";


/*Google Fontsの読み込み
---------------------------------------------------------------------------*/
@import url('https://fonts.googleapis.com/css2?family=M+PLUS+1:wght@100..900&display=swap');

/*Font Awesomeの読み込み
---------------------------------------------------------------------------*/
@import url("https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css");

/*lightbox.cssの読み込み
---------------------------------------------------------------------------*/
@import url(https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.10.0/css/lightbox.css);


/*opa1のキーフレーム設定
---------------------------------------------------------------------------*/
@keyframes opa1 {
                0% {opacity: 0;}
                100% {opacity: 1;}
}


/*animation1のキーフレーム設定（開閉ブロックのアニメーションに使用）
---------------------------------------------------------------------------*/
@keyframes animation1 {
                0% {left: -200px;}
                100% {left: 0px;}
}



/*全体の設定
---------------------------------------------------------------------------*/
body * {box-sizing: border-box;}
html,body {
                height: 100%;
                font-size: 13px;	/*基準となるフォントサイズ。*/
}

                /*画面幅900px以上の追加指定*/
                @media screen and (min-width:900px) {

                html, body {
                                font-size: 14px;	/*基準となるフォントサイズ。*/
                }

                }/*追加指定ここまで*/

body {
                font-family: "M PLUS 1", "ヒラギノ角ゴ Pro W3", "Hiragino Kaku Gothic Pro", "メイリオ", Meiryo, Osaka, "ＭＳ Ｐゴシック", "MS PGothic", sans-serif;	/*フォント種類*/
                font-weight: 300;
                font-optical-sizing: auto;
                letter-spacing: 0.1rem;
                text-indent: 0.1rem;
                -webkit-text-size-adjust: none;
                margin: 0;padding: 0;
                line-height: 2.5;		/*行間*/
                background: #24385b;		/*背景色*/
                color: #fff;			/*文字色*/
}

/*リセット*/
figure {margin: 0;}
dd {margin: 0;}
nav,ul,li {margin: 0;padding: 0;}
nav ul {list-style: none;}
h1,h2,h3,h4 {font-weight: 400;}

/*table全般の設定*/
table {border-collapse:collapse;}

/*画像全般の設定*/
img {border: none;max-width: 100%;height: auto;vertical-align: middle;}

/*videoタグ*/
video {max-width: 100%;}

/*iframeタグ*/
iframe {width: 100%;}

/*他*/
input {font-size: 1rem;}
strong {font-weight: 500;}


/*リンクテキスト全般の設定
---------------------------------------------------------------------------*/
a {
                color: #fff;	/*文字色*/
                transition: 0.3s;	/*hoverまでにかける時間。0.3秒。*/
}

/*マウスオン時*/
a:hover {
                text-decoration: none;
}


/*header（ロゴとメニューが入ったブロック）
---------------------------------------------------------------------------*/
header {
                width: 250px;		/*幅*/
                padding: 0 2vw;		/*ヘッダー内の余白。上下、左右への順番。*/
                margin-top: 5vw;	/*ヘッダーの上に空けるスペース*/
                text-align: center;	/*テキストをセンタリング*/
}

/*ロゴ*/
header #logo img {display: block;}
header #logo {
                padding: 0;margin: 0;
}

/*ロゴ下の小文字*/
header #logo span {
                display: block;
                font-size: 0.7rem;	/*文字サイズを70%に*/
}

                /*画面幅900px以上の追加指定*/
                @media screen and (min-width:900px) {

                header {
                                position: fixed;	/*スクロールしても動かないようにする設定*/
                                left: 0px;
                                top: 0px;
                }

                }/*追加指定ここまで*/

                /*画面の高さが500px以下の追加指定*/
                @media screen and (max-height:500px) {

                header {
                                position: absolute;	/*メニューが切れて見えなくならないように、fixedを中止する*/
                }

                }/*追加指定ここまで*/


/*mainブロック（右側のsectionを囲むブロック）
---------------------------------------------------------------------------*/

                /*画面幅900px以上の追加指定*/
                @media screen and (min-width:900px) {

                main {
                                margin-left: 250px;	/*headerのwidthに合わせる*/
                }

                }/*追加指定ここまで*/


/*main内のh2*/
main h2 {
                font-size: 2.5rem;	/*文字サイズを2.5倍*/
}

/*main内のh3*/
main h3 {
                font-size: 1.5rem;	/*文字サイズを1.5倍*/
}



/*メニューブロック初期設定
---------------------------------------------------------------------------*/
/*メニューをデフォルトで非表示*/
#menubar {display: none;}

/*上で非表示にしたメニューを表示させる為の設定*/
.large-screen #menubar {display: block;}
.small-screen #menubar.display-block {display: block;}

/*3本バーをデフォルトで非表示*/
#menubar_hdr.display-none {display: none;}


/*メニュー
---------------------------------------------------------------------------*/
/*メニューブロック全体*/
#menubar ul {
                margin: 3rem 0;	/*メニューブロックの外側に空けるスペース*/
}

/*メニュー一個あたり*/
#menubar nav a {
                text-decoration: none;display: block;
                background: rgba(0,0,0,0.5);	/*背景色。0,0,0は黒のことで0.5は色が50%出た状態。*/
                border: 1px solid rgba(255,255,255,0.3);	/*枠線の幅、線種、色。255,255,255は白のことで0.3は色が30%出た状態。*/
                color: #fff;		/*文字色*/
                padding: 0.5rem;	/*余白*/
                margin: 0.5rem 0;	/*メニューの外側に空けるスペース。上下、左右。*/
                border-radius: 5px;	/*角を丸くする指定*/
}

/*マウスオン次*/
#menubar nav a:hover {
                background: rgba(0,0,0,0.9);	/*背景色。透明度を変更して濃くします。*/
                border: 1px solid rgba(255,255,255,0.9);	/*枠線。透明度を変更して濃くします。*/
}

/*900px以下画面でのメニュー
---------------------------------------------------------------------------*/
/*メニューブロック全体*/
.small-screen #menubar.display-block {
                position: fixed;overflow: auto;z-index: 100;
                left: 0px;top: 0px;
                width: 100%;
                height: 100%;
                padding-top: 80px;
                background: rgba(0,0,0,0.8);		/*背景色*/
                animation: animation1 0.2s both;	/*animation1を実行する。0.2sは0.2秒の事。*/
}
.small-screen #menubar ul {
                margin: 3rem;	/*メニューブロックの外側に空けるスペース*/
}


/*３本バー（ハンバーガー）アイコン設定
---------------------------------------------------------------------------*/
/*３本バーを囲むブロック*/
#menubar_hdr {
                animation: opa1 0s 0.2s both;
                position: fixed;z-index: 101;
                cursor: pointer;
                right: 30px;			/*右からの配置場所指定*/
                top: 30px;				/*上からの配置場所指定*/
                padding: 16px 14px;		/*上下、左右への余白*/
                width: 46px;			/*幅（３本バーが出ている場合の幅になります）*/
                height: 46px;			/*高さ*/
                display: flex;					/*flexボックスを使う指定*/
                flex-direction: column;			/*子要素（３本バー）部分。flexはデフォルトで横並びになるので、それを縦並びに変更。*/
                justify-content: space-between;	/*並びかたの種類の指定*/
                background: #d30c44;	/*背景色*/
}

/*バー１本あたりの設定*/
#menubar_hdr span {
                display: block;
                transition: 0.3s;	/*アニメーションにかける時間。0.3秒。*/
                border-top: 1.5px solid #fff;	/*線の幅、線種、色*/
}

/*×印が出ている状態の設定。※１本目および２本目のバーの共通設定。*/
#menubar_hdr.ham span:nth-of-type(1),
#menubar_hdr.ham span:nth-of-type(3) {
                transform-origin: center center;	/*変形の起点。センターに。*/
                width: 20px;						/*バーの幅*/
}

/*×印が出ている状態の設定。※１本目のバー。*/
#menubar_hdr.ham span:nth-of-type(1){
                transform: rotate(45deg) translate(3.8px, 5px);	/*回転45°と、X軸Y軸への移動距離の指定*/
}

/*×印が出ている状態の設定。※３本目のバー。*/
#menubar_hdr.ham span:nth-of-type(3){
                transform: rotate(-45deg) translate(3.8px, -5px);	/*回転-45°と、X軸Y軸への移動距離の指定*/
}

/*×印が出ている状態の設定。※２本目のバー。*/
#menubar_hdr.ham span:nth-of-type(2){
                display: none;	/*２本目は使わないので非表示にする*/
}


/*メニュー内にあるソーシャルメディアのアイコン
---------------------------------------------------------------------------*/
ul.icons {
                list-style: none;
                margin: 0;padding: 0;
                display: flex;
                justify-content: center;
}
ul.icons li {
                margin-right: 10px;	/*アイコン同士の余白*/
}
ul.icons i {
                font-size: 20px;	/*Font Awesomeのアイコンサイズ*/
}


/*section
---------------------------------------------------------------------------*/
/*フェード設定*/
.section::before {
                opacity: 0; /* 初期状態では非表示 */
                transition: opacity 1s; /* 1秒かけてフェードイン/フェードアウト */
}
.section.active::before {
                opacity: 1; /* フェードイン状態 */
}
.section.inactive::before {
                opacity: 0; /* フェードアウト状態 */
}

/*section要素*/
section {
                padding: 2vw 5vw;	/*ボックス内の余白。上下、左右への順番。*/
}

/*４つのsectionブロックの共通設定*/
#section1,#section2,#section3,#section4 {
                min-height: calc(100dvh - 50px);	/*最低の高さ。100dvhは画面の高さ100%のこと。50pxは下のmarginの値。*/
                margin-bottom: 50px;	/*ボックスの下に空けるスペース*/
                padding: 5vw;			/*ボックス内の余白*/
}
                /*画面幅900px以上の追加指定*/
                @media screen and (min-width:900px) {

                #section1,#section2,#section3,#section4 {
                                border-radius: 3vw 0 0 3vw;	/*角丸の指定。左上、右上、右下、左下への順番。*/
                }

                }/*追加指定ここまで*/


/*背景画像を置く為の設定*/
#section1::before,#section2::before,#section3::before,#section4::before {
                content: '';
                position: fixed;z-index: -1;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
}

/*section1の設定*/
#section1 {
    margin-top: 2em;
    background-color: rgba(230, 160, 130, 0.7);	/*背景色。３つ目までの数字はrgbでの色指定。最後の小数点は透明度。*/
                color: #fff;	/*文字色*/
}
#section1::before {
                background: url({{ asset('img/section1.jpg') }}) no-repeat center center / cover;	/*背景画像の読み込み*/
}

/*section2の設定*/
#section2 {
                background-color: rgba(20,120,180,0.7);	/*背景色。３つ目までの数字はrgbでの色指定。最後の小数点は透明度。*/
                color: #fff;	/*文字色*/
}
#section2::before {
                background: url({{ asset('img/section2.jpg') }}) no-repeat center center / cover;	/*背景画像の読み込み*/
}

/*section3の設定*/
#section3 {
                background-color: rgba(21,106,77,0.7);	/*背景色。３つ目までの数字はrgbでの色指定。最後の小数点は透明度。*/
                color: #fff;	/*文字色*/
}
#section3::before {
                background: url({{ asset('img/section3.jpg') }}) no-repeat center center / cover;	/*背景画像の読み込み*/
}

/*section4の設定*/
#section4 {
    background-color: rgba(135, 238, 214, 0.7);	/*背景色。３つ目までの数字はrgbでの色指定。最後の小数点は透明度。*/
                color: #fff;	/*文字色*/
}
#section4::before {
                background: #173f47;	/*section4だけは背景画像ではなく、単に背景色だけ指定しました。*/
}

/*背景色を入れない場合（画像だけを表示したい場合）*/
.no-bgcolor {background-color: transparent !important;}


/*フッター設定
---------------------------------------------------------------------------*/
footer small {font-size: 100%;}
footer {
                font-size: 0.8rem;
                text-align: center;		/*内容をセンタリング*/
                padding-bottom: 1rem;
}

/*リンクテキスト*/
footer a {color: inherit;text-decoration: none;}


/*お知らせブロック
---------------------------------------------------------------------------*/
/*記事の下に空ける余白*/
.new dd {
                padding-bottom: 1rem;
}

/*ブロック内のspan。日付の横のアイコン的な部分の共通設定*/
.new dt span {
                display: inline-block;
                text-align: center;
                line-height: 1.8;		/*行間（アイコンの高さ）*/
                border-radius: 3px;		/*角を丸くする指定*/
                padding: 0 0.5rem;		/*上下、左右へのブロック内の余白*/
                width: 6rem;			/*幅。６文字分。*/
                transform: scale(0.8);	/*80%のサイズに縮小*/
                background: rgba(255,255,255,0.8);		/*背景色*/
                color: #333;			/*文字色*/
}

                /*画面幅700px以上の追加指定*/
                @media screen and (min-width:700px) {

                /*ブロック全体*/
                .new {
                                display: grid;	/*gridを使う指定*/
                                grid-template-columns: auto 1fr;	/*横並びの指定。日付とアイコン部分の幅は自動で、内容が入るブロックは残り幅一杯とる。*/
                }

                }/*追加指定ここまで*/


/*list-grid（gallery.htmlでサムネイルを表示している部分の設定です）
---------------------------------------------------------------------------*/
/*listブロックを囲む外側のボックス*/
.list-grid-trimming {
                display: grid;
                grid-template-columns: repeat(4, 1fr);	/*ここの「4」の数字が横に並べる数です。3列がいいなら(3, 1fr)です。*/
                gap: 1rem;	/*マージン的な数値。サムネイル間を１文字分あけます。*/
}

/*ボックスを正方形にトリミングする為の指定なので変更しない。*/
.list-grid-trimming .list {
                position: relative;
                overflow: hidden;
                height: 0;
                padding-top: 100%;
}
.list-grid-trimming .list a {
                display: block;
                position: absolute;
                left: 0px;
                top: 0px;
                width: 100%;
                height: 100%;
}
.list-grid-trimming .list img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                object-position: center;
                transition: 0.5s;	/*マウスオン時にかける時間。0.5秒。*/
}

/*マウスオン時の画像*/
.list-grid-trimming .list img:hover {
                transform: scale(1.1);	/*1.1倍に拡大*/
                filter: contrast(1.3);	/*コントラストを1.3倍*/
}


/*PAGE TOP（↑）設定
---------------------------------------------------------------------------*/
.pagetop-show {display: block;}

/*ボタンの設定*/
.pagetop a {
                display: block;text-decoration: none;text-align: center;z-index: 99;
                animation: opa1 0.2s 0.2s both;	/*一瞬ボタンが出ちゃうのを隠す為の応急措置*/
                position: fixed;	/*スクロールに追従しない(固定で表示)為の設定*/
                right: 20px;		/*右からの配置場所指定*/
                bottom: 20px;		/*下からの配置場所指定*/
                color: #fff;		/*文字色*/
                font-size: 1.5rem;	/*文字サイズ*/
                background: rgba(0,0,0,0.2);	/*背景色。0,0,0は黒の事で0.2は色が20%出た状態。*/
                width: 60px;		/*幅*/
                line-height: 60px;	/*高さ*/
                border-radius: 50%;	/*円形にする*/
}

/*その他
---------------------------------------------------------------------------*/
.clearfix::after {content: "";display: block;clear: both;}
.l {text-align: left !important;}
.c {text-align: center !important;}
.r {text-align: right !important;}
.ws {width: 95%;display: block;}
.wl {width: 95%;display: block;}
.mb0 {margin-bottom: 0px !important;}
.mb30 {margin-bottom: 30px !important;}
.look {display: inline-block;padding: 0px 10px;background: #000;border: 1px solid #fff;border-radius: 3px;margin: 5px 0; word-break: break-all;}
.small {font-size: 0.75em;}
.large {font-size: 2em; letter-spacing: 0.1em;}
.color-check, .color-check a {color: #ffcf0d;}
.pc {display: none;}
.dn {display: none !important;}
.block {display: block !important;}

                /*画面幅900px以上の追加指定*/
                @media screen and (min-width:900px) {

                                .ws {width: 48%;display: inline;}
                                .sh {display: none;}
                                .pc {display: block;}

                }/*画面幅900px以上の追加指定ここまで*/

</style>
</head>
<body>

<header>

<h1>seiei VR</h1>

<!--開閉ブロック-->
<div id="menubar">

<nav>
<ul>
<li><a href="#">seiei content</a></li>
<li><a href="#section2">external content</a></li>
<!-- <li><a href="#section3"></a></li>
<li><a href="#section4">Gallery</a></li> -->
</ul>
</nav>


</div>
<!--/#menubar-->

</header>

<main>



<!-- ここからsection1 -->
<section id="section1" class="section">
<p>seiei content</p>

<dl class="new">

<!-- <dt>2024/07/16<span>movie</span></dt>
<dd><a href="movie01.html">tennis Club</a></dd>

<dt>2024/07/23<span>movie</span></dt>
<dd><a href="movie02.html">table tennis Club</a></dd> -->
<dt>2024/08/26<span>images</span></dt>
<dd><a href="anime1.html">vr change animation 1</a></dd>
                
<dt>2024/07/25<span>images</span></dt>
<dd><a href="360images.html">360image slide show</a></dd>

<dt>2024/07/25<span>games</span></dt>
<dd><a href="akaei01.html">akaei01</a></dd>

<dt>2024/07/26<span>artwork</span></dt>
<dd><a href="artwork01.html"> students' work 01</a></dd>

<dt>2024/07/26<span>artwork</span></dt>
<dd><a href="artwork02.html"> students' work 02</a></dd>

<dt>2024/07/26<span>artwork</span></dt>
<dd><a href="artwork03.html"> students' work 03</a></dd>

<dt>2024/07/26<span>artwork</span></dt>
<dd><a href="artwork04.html"> students' work 04</a></dd>
</section>



<!-- ここからsection2 -->
<section id="section2" class="section">
    <p>vr games</p>

    <dl class="new">

    <dt>2024/07/16<span>games</span></dt>
    <dd><a href="https://heyvr.io/arcade/games/archery-training">Archery Training</a></dd>

    <dt>2024/07/23<span>games</span></dt>
    <dd><a href="https://moonrider.xyz/">moonrider</a></dd>

    </dl>
</section>



<!-- ここからsection3 -->
<!-- <section id="section3" class="section">

<h2>Activity Report</h2>
<p>これはsection3のコンテンツです。</p>

<dl class="new">

<dt>2024/05/16<span>その他</span></dt>
<dd>htmlの最後のタグ数行がおかしくなってしまっていました。既にご利用中のお客様は最新テンプレをDLして頂き、htmlの下の数行部分を入れ替えて下さい。</dd>

<dt>2024/05/05<span>その他</span></dt>
<dd>css冒頭の設定が、「body &gt; * {box-sizing: border-box;}」となっておりましたが、「body * {box-sizing: border-box;}」に修正。</dd>


</dl>

</section> -->


<!-- ここからsection4 -->
<!-- <section id="section4" class="section">

    <h2>Profile4</h2>
    <p>これはsection4のコンテンツです。</p>

    <h2>テンプレートのご利用前に必ずお読み下さい</h2>

    <h3>利用規約のご案内</h3>
    <p>このテンプレートは、<a href="https://template-party.com/">Template Party</a>にて無料配布している『アーティスト(画家・作家・音楽家・芸能人など)向け 無料ホームページテンプレート tp_artist2』です。必ずダウンロード先のサイトの<a href="https://template-party.com/read.html">利用規約</a>をご一読の上でご利用下さい。</p>
    <h3>HP最下部の著作表示『Web Design:Template-Party』は無断で削除しないで下さい。</h3>
    <p>わざと見えなく加工する事も禁止です。</p>
    <h3>下部の著作を外したい場合は</h3>
    <p><a href="https://template-party.com/">Template-Party</a>の<a href="https://template-party.com/member.html">ライセンス契約</a>を行う事でHP下部の著作を外す事ができます。</p>

    <h3>当テンプレートの詳しい使い方は</h3>
    <p><a href="about.html">こちらをご覧下さい。</a></p>

</section> -->





</main>

<footer>
<small>Copyright&copy; <a href="index.html">Artist Web Site</a> All Rights Reserved.</small>
<span class="pr"><a href="https://template-party.com/" target="_blank">《Web Design:Template-Party》</a></span>
</footer>

<!--ページの上部へ戻るボタン-->
<div class="pagetop"><a href="#"><i class="fas fa-angle-double-up"></i></a></div>

<!--開閉ボタン（ハンバーガーアイコン）-->
<div id="menubar_hdr">
<span></span><span></span><span></span>
</div>

<!--jQueryの読み込み-->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

<!--lightbox用jsファイルの読み込み-->
<script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.10.0/js/lightbox-plus-jquery.min.js"></script>

<script>
    //ここにJavaScriptのコードを記述
    //===============================================================
// debounce関数
//===============================================================
function debounce(func, wait) {
    var timeout;
    return function() {
        var context = this, args = arguments;
        var later = function() {
            timeout = null;
            func.apply(context, args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}


//===============================================================
// メニュー関連
//===============================================================

// 変数でセレクタを管理
var $menubar = $('#menubar');
var $menubarHdr = $('#menubar_hdr');

// menu
$(window).on("load resize", debounce(function() {
    if(window.innerWidth < 900) {
        // 小さな端末用の処理
        $('body').addClass('small-screen').removeClass('large-screen');
        $menubar.addClass('display-none').removeClass('display-block');
        $menubarHdr.removeClass('display-none ham').addClass('display-block');
    } else {
        // 大きな端末用の処理
        $('body').addClass('large-screen').removeClass('small-screen');
        $menubar.addClass('display-block').removeClass('display-none');
        $menubarHdr.removeClass('display-block').addClass('display-none');

        // ドロップダウンメニューが開いていれば、それを閉じる
        $('.ddmenu_parent > ul').hide();
    }
}, 10));

$(function() {

    // ハンバーガーメニューをクリックした際の処理
    $menubarHdr.click(function() {
        $(this).toggleClass('ham');
        if ($(this).hasClass('ham')) {
            $menubar.addClass('display-block');
        } else {
            $menubar.removeClass('display-block');
        }
    });

    // アンカーリンクの場合にメニューを閉じる処理
    $menubar.find('a[href*="#"]').click(function() {
        $menubar.removeClass('display-block');
        $menubarHdr.removeClass('ham');
    });

    // ドロップダウンの親liタグ（空のリンクを持つaタグのデフォルト動作を防止）
                $menubar.find('a[href=""]').click(function() {
                                return false;
                });

                // ドロップダウンメニューの処理
    $menubar.find('li:has(ul)').addClass('ddmenu_parent');
    $('.ddmenu_parent > a').addClass('ddmenu');

// タッチ開始位置を格納する変数
var touchStartY = 0;

// タッチデバイス用
$('.ddmenu').on('touchstart', function(e) {
    // タッチ開始位置を記録
    touchStartY = e.originalEvent.touches[0].clientY;
}).on('touchend', function(e) {
    // タッチ終了時の位置を取得
    var touchEndY = e.originalEvent.changedTouches[0].clientY;

    // タッチ開始位置とタッチ終了位置の差分を計算
    var touchDifference = touchStartY - touchEndY;

    // スクロール動作でない（差分が小さい）場合にのみドロップダウンを制御
    if (Math.abs(touchDifference) < 10) { // 10px以下の移動ならタップとみなす
        var $nextUl = $(this).next('ul');
        if ($nextUl.is(':visible')) {
            $nextUl.stop().hide();
        } else {
            $nextUl.stop().show();
        }
        $('.ddmenu').not(this).next('ul').hide();
        return false; // ドロップダウンのリンクがフォローされるのを防ぐ
    }
});

    //PC用
    $('.ddmenu_parent').hover(function() {
        $(this).children('ul').stop().show();
    }, function() {
        $(this).children('ul').stop().hide();
    });

    // ドロップダウンをページ内リンクで使った場合に、ドロップダウンを閉じる
    $('.ddmenu_parent ul a').click(function() {
        $('.ddmenu_parent > ul').hide();
    });

});


//===============================================================
// 小さなメニューが開いている際のみ、body要素のスクロールを禁止。
//===============================================================
$(document).ready(function() {
  function toggleBodyScroll() {
    // 条件をチェック
    if ($('#menubar_hdr').hasClass('ham') && !$('#menubar_hdr').hasClass('display-none')) {
      // #menubar_hdr が 'ham' クラスを持ち、かつ 'display-none' クラスを持たない場合、スクロールを禁止
      $('body').css({
        overflow: 'hidden',
        height: '100%'
      });
    } else {
      // その他の場合、スクロールを再び可能に
      $('body').css({
        overflow: '',
        height: ''
      });
    }
  }

  // 初期ロード時にチェックを実行
  toggleBodyScroll();

  // クラスが動的に変更されることを想定して、MutationObserverを使用
  const observer = new MutationObserver(toggleBodyScroll);
  observer.observe(document.getElementById('menubar_hdr'), { attributes: true, attributeFilter: ['class'] });
});


//===============================================================
// スムーススクロール（※バージョン2024-1）※通常タイプ
//===============================================================
$(function() {
    // ページ上部へ戻るボタンのセレクター
    var topButton = $('.pagetop');
    // ページトップボタン表示用のクラス名
    var scrollShow = 'pagetop-show';

    // スムーススクロールを実行する関数
    // targetにはスクロール先の要素のセレクターまたは'#'（ページトップ）を指定
    function smoothScroll(target) {
        // スクロール先の位置を計算（ページトップの場合は0、それ以外は要素の位置）
        var scrollTo = target === '#' ? 0 : $(target).offset().top - 25;
        // アニメーションでスムーススクロールを実行
        $('html, body').animate({scrollTop: scrollTo}, 500);
    }

    // ページ内リンクとページトップへ戻るボタンにクリックイベントを設定
    $('a[href^="#"], .pagetop').click(function(e) {
        e.preventDefault(); // デフォルトのアンカー動作をキャンセル
        var id = $(this).attr('href') || '#'; // クリックされた要素のhref属性を取得、なければ'#'
        smoothScroll(id); // スムーススクロールを実行
    });

    // スクロールに応じてページトップボタンの表示/非表示を切り替え
    $(topButton).hide(); // 初期状態ではボタンを隠す
    $(window).scroll(function() {
        if($(this).scrollTop() >= 300) { // スクロール位置が300pxを超えたら
            $(topButton).fadeIn().addClass(scrollShow); // ボタンを表示
        } else {
            $(topButton).fadeOut().removeClass(scrollShow); // それ以外では非表示
        }
    });

    // ページロード時にURLのハッシュが存在する場合の処理
    if(window.location.hash) {
        // ページの最上部に即時スクロールする
        $('html, body').scrollTop(0);
        // 少し遅延させてからスムーススクロールを実行
        setTimeout(function() {
            smoothScroll(window.location.hash);
        }, 10);
    }
});


//===============================================================
// 汎用開閉処理
//===============================================================
$(function() {
                $('.openclose').next().hide();
                $('.openclose').click(function() {
                                $(this).next().slideToggle();
                                $('.openclose').not(this).next().slideUp();
                });
});


//===============================================================
// 背景切り替え
//===============================================================
$(document).ready(function () {
  function checkVisibility() {
    const viewportHeight = $(window).height(); // ビューポートの高さ
    const scrollTop = $(window).scrollTop(); // 現在のスクロール位置

    $(".section").each(function () {
      const sectionTop = $(this).offset().top; // セクションの上端位置
      const sectionHeight = $(this).outerHeight(); // セクションの高さ

      // セクションの位置をチェックして画像を切り替える
      if (sectionTop < scrollTop + viewportHeight * 0.6 && sectionTop + sectionHeight > scrollTop + viewportHeight * 0.4) {
        $(this).addClass("active").removeClass("inactive"); // フェードイン
      } else {
        $(this).addClass("inactive").removeClass("active"); // フェードアウト
      }
    });
  }

  // スクロールイベントでチェック
  $(window).on("scroll", checkVisibility);

  // 初期チェック
  checkVisibility();
});

</script>

</body>
</html>