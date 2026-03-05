<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WooOrder extends Model
{
    /** @use HasFactory<\Database\Factories\WooOrderFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'woo_shop_id',
        'external_order_id',
        'order_number',
        'status',
        'currency',
        'total',
        'customer_name',
        'customer_email',
        'order_created_at',
        'order_paid_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'order_created_at' => 'datetime',
            'order_paid_at' => 'datetime',
        ];
    }

    public function wooShop(): BelongsTo
    {
        return $this->belongsTo(WooShop::class);
    }
}
