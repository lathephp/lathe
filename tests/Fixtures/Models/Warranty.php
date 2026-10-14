<?php

namespace Lathe\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $product_id
 * @property string $type
 * @property int $months
 */
class Warranty extends Model
{
    protected $guarded = ['id'];

    public $timestamps = false;

    /**
     * @return BelongsTo<WarrantyProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(WarrantyProduct::class, 'product_id');
    }
}
