<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ARスタンプラリー202611 商品券抽選の確認コード</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f8;color:#222;">
    <div style="max-width:560px;margin:0 auto;padding:24px 16px;font-family:-apple-system,BlinkMacSystemFont,'Helvetica Neue',Arial,'Hiragino Sans','Noto Sans JP',sans-serif;">
        <div style="background:#fff;border-radius:12px;padding:22px 20px;border:1px solid #e3e8ef;">
            <p style="margin:0 0 6px;font-size:13px;color:#666;">ARスタンプラリー202611</p>
            @if (($purpose ?? 'apply') === 'change')
                <h1 style="margin:0 0 14px;font-size:19px;color:#111;">市内共通商品券の抽選 メールアドレス変更 確認コード</h1>

                <p style="margin:0 0 14px;font-size:14px;line-height:1.7;">
                    応募済みのメールアドレスを変更するための確認コードをお送りします。<br>
                    スタンプ帳内の「登録したメールアドレスを変更する」画面に入力してください。<br>
                    <strong>このコードを入力しなければメールアドレスは変わりません。</strong>
                </p>
            @else
                <h1 style="margin:0 0 14px;font-size:19px;color:#111;">市内共通商品券の抽選応募 確認コード</h1>

                <p style="margin:0 0 14px;font-size:14px;line-height:1.7;">
                    抽選応募の確認コードをお送りします。スタンプ帳内の応募画面に入力してください。
                </p>
            @endif

            <p style="margin:0 0 6px;font-size:13px;color:#666;">確認コード（6 桁）</p>
            <p style="margin:0 0 16px;font-size:30px;font-weight:800;letter-spacing:.18em;color:#111;text-align:center;">{{ $code }}</p>

            <p style="margin:0 0 16px;font-size:13px;line-height:1.7;color:#444;">
                このコードの有効期限は <strong>{{ $ttlMinutes }} 分</strong> です。<br>
                有効期限を過ぎた場合やコードが変わらない場合は、応募画面で「コードを再送信」から新しいコードを取得してください。
            </p>

            <hr style="border:none;border-top:1px solid #e3e8ef;margin:16px 0;">

            <p style="margin:0;font-size:12px;line-height:1.7;color:#777;">
                このメールに心当たりがない場合は、コードを入力せずにこのメールを破棄してください。<br>
                このメールは送信専用です。返信しても応募は完了しません。
            </p>
        </div>
    </div>
</body>
</html>
