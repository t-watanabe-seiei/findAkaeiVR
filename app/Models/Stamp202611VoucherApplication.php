<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ARstampRally202611 市内共通商品券の抽選応募（design.md §5.4）。
 *
 * 1 fingerprint = 1 応募 / 1 email = 1 応募（FR-44）。
 * weight（当選倍率 1 / 2 / 3）は収集種数の増加に伴い StampRally202611Controller::syncVoucherWeight()
 * で上方更新される（下方更新しない・FR-43）。
 */
class Stamp202611VoucherApplication extends Model
{
    use HasFactory;

    protected $table = 'stamp202611_voucher_applications';

    protected $fillable = [
        'session_id',
        'fingerprint',
        'email',
        'name',
        'stamp_count',
        'tier',
        'weight',
        'user_agent',
        'ip_address',
        'device_info',
        'applied_at',
    ];

    protected $casts = [
        'device_info' => 'array',
        'stamp_count' => 'integer',
        'tier' => 'integer',
        'weight' => 'integer',
        'applied_at' => 'datetime',
    ];
}
