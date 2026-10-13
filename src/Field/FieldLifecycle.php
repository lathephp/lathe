<?php

namespace Lathe\Field;

use Closure;
use Lathe\Http\Requests\LatheRequest;

/**
 * How a field value travels: read from the record, sent to the page, read from the request, stored.
 */
interface FieldLifecycle
{
    /** The raw value on the record. */
    public function extract(object $record, string $attribute): mixed;

    /** The wire value of a raw value. */
    public function present(mixed $raw, object $record, string $attribute): mixed;

    /** The PHP value of the request input. */
    public function parse(LatheRequest $request, string $attribute): mixed;

    /** Set the value on the model; a returned Closure runs after save, inside the write's transaction. */
    public function persist(object $model, string $attribute, mixed $value): ?Closure;
}
