<?php

namespace Lathe\Field\Repeater;

use Closure;
use Lathe\Field\FieldLifecycle;
use Lathe\Field\RepeaterField;
use Lathe\Http\Requests\LatheRequest;

/**
 * A repeater's value: the storage preset reads and writes the rows, the field turns
 * them into wire rows and back.
 *
 * @internal
 */
class RowsLifecycle implements FieldLifecycle
{
    public function __construct(protected RepeaterField $field, protected FieldLifecycle $storage)
    {
        //
    }

    public function extract(object $record, string $attribute): mixed
    {
        return $this->storage->extract($record, $attribute);
    }

    public function present(mixed $raw, object $record, string $attribute): mixed
    {
        return $this->field->presentRows(is_array($raw) ? array_values($raw) : []);
    }

    public function parse(LatheRequest $request, string $attribute): mixed
    {
        return $this->field->parseRows($request, $attribute);
    }

    public function persist(object $model, string $attribute, mixed $value): ?Closure
    {
        return $this->storage->persist($model, $attribute, $value);
    }
}
