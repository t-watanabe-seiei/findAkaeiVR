<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * ARstampRally202611 市内共通商品券の抽選応募用 確認コードメール（requirements.md FR-61 / design.md §5.7）。
 *
 * purpose = 'apply' は抽選応募の確認（FR-61）、'change' は応募済みメールアドレスの変更確認（FR-63 / design.md §5.8）。
 * 送信元は config('mail.from.address')（本番は subscription@seiei.online）。
 * コードは本文にのみ記載し、DB には保存しない（Cache に TTL 付きで保持）。
 *
 * ShouldQueue を付けていない理由: Xserver 環境でキューワーカー（supervisor 等）を常駐させないため、
 * リクエスト内で同期送信する（design.md §5.7）。
 */
class Stamp202611VoucherCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param string $code       6 桁の確認コード
     * @param int    $ttlMinutes コードの有効期限（分）
     * @param string $purpose    'apply'（抽選応募）または 'change'（メールアドレス変更）
     */
    public function __construct(
        public readonly string $code,
        public readonly int $ttlMinutes,
        public readonly string $purpose = 'apply',
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->purpose === 'change'
                ? '【ARスタンプラリー202611】商品券抽選のメールアドレス変更 確認コード'
                : '【ARスタンプラリー202611】商品券抽選の確認コード',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.stamp202611.voucher-code',
            with: [
                'code' => $this->code,
                'ttlMinutes' => $this->ttlMinutes,
                'purpose' => $this->purpose,
            ],
        );
    }
}
