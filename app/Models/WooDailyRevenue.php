<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WooDailyRevenue extends Model
{
    /** @use HasFactory<\Database\Factories\WooDailyRevenueFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'woo_shop_id',
        'revenue_date',
        'currency',
        'revenue_total',
        'orders_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'revenue_date' => 'date',
            'revenue_total' => 'decimal:2',
            'orders_count' => 'integer',
        ];
    }

    public function wooShop(): BelongsTo
    {
        return $this->belongsTo(WooShop::class);
    }
}
