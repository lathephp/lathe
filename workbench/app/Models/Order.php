<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Lathe\ActionEvents\Actionable;

/**
 * @property int $id
 * @property int $customer_id
 * @property string $number
 * @property string $status
 * @property int $total_cents
 * @property Carbon $placed_at
 * @property-read Customer $customer
 */
#[Fillable(['customer_id', 'number', 'status', 'total_cents', 'placed_at'])]
class Order extends Model
{
    use Actionable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_cents' => 'integer',
            'placed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
