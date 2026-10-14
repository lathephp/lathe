<?php

namespace Lathe\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Workbench\App\Models\Product;

/**
 * A workbench product with one warranty, for the hasOne field.
 */
class WarrantyProduct extends Product
{
    protected $table = 'products';

    /**
     * @return HasOne<Warranty, $this>
     */
    public function warranty(): HasOne
    {
        return $this->hasOne(Warranty::class, 'product_id');
    }

    /**
     * @return HasOne<Warranty, $this>
     */
    public function latestWarranty(): HasOne
    {
        return $this->hasOne(Warranty::class, 'product_id')->latestOfMany();
    }
}
