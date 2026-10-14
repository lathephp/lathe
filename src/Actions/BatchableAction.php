<?php

namespace Lathe\Actions;

use Illuminate\Bus\PendingBatch;

/**
 * An action that adds its own then, catch and finally callbacks to the batch of a queued run.
 */
interface BatchableAction
{
    public function withBatch(ActionFields $fields, PendingBatch $batch): void;
}
