<?php

namespace Workbench\App\Lathe\Actions;

use Lathe\Actions\Import\ImportAction;
use Lathe\Actions\Import\ImportColumn;
use Workbench\App\Models\Category;
use Workbench\App\Models\Product;

class ImportProducts extends ImportAction
{
    public function __construct()
    {
        parent::__construct();

        $this->withColumns(
            ImportColumn::make('name')->rules('required'),
            ImportColumn::make('sku')->rules('required'),
            ImportColumn::make('price')->numeric(2)->rules('required', 'numeric'),
        )
            ->upsertBy('sku')
            ->beforeSave(function (array $row) {
                if (array_key_exists('price', $row)) {
                    $row['price_cents'] = is_numeric($row['price']) ? (int) round($row['price'] * 100) : null;
                    unset($row['price']);
                }

                // The file has no category, so a new product goes in the first one.
                if (is_string($row['sku'] ?? null) && ! Product::where('sku', $row['sku'])->exists()) {
                    $row['category_id'] ??= Category::orderBy('id')->value('id');
                }

                return $row;
            });
    }
}
