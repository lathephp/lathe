<?php

namespace Workbench\App\Lathe\Fields;

use Lathe\Field\Field;

/**
 * A rating of 1 to 5 that the workbench-star-rating component draws as stars.
 */
class StarRating extends Field
{
    protected string $component = 'workbench-star-rating';
}
