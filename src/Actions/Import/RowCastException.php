<?php

namespace Lathe\Actions\Import;

use Lathe\Exceptions\LatheException;

/**
 * A cell that cannot be cast to its column type. The import job turns it into a failed row.
 */
class RowCastException extends LatheException
{
    //
}
