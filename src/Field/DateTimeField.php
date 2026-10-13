<?php

namespace Lathe\Field;

use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Support\Timezone;

/**
 * A moment. It travels out as UTC ISO 8601 and is stored in the app timezone.
 *
 * The form converts on the server, not in the browser. The input shows the schema's localValue, the
 * moment as wall time in the user's timezone (Lathe::resolveUserTimezone(), else the app's), and
 * sends back either that wall time ("2026-10-14T09:00") or an unedited UTC value ("...Z" or with an
 * offset). A string with no offset is read in the user's timezone, so 09:00 in Pacific/Kiritimati
 * stores 2026-10-13T19:00:00Z. min and max are compared as given, so write them as wall time.
 */
class DateTimeField extends DateField
{
    protected string $component = 'date-time-field';

    protected const string FORMAT = 'Y-m-d\TH:i';

    public function resolve(object $record, ?string $attribute = null): mixed
    {
        return Field::resolve($record, $attribute);
    }

    public function fill(LatheRequest $request, object $model, ?string $requestAttribute = null): ?Closure
    {
        $immutable = $model instanceof Model && $model->exists && $this->isImmutable($request, $model);

        if ($immutable || $this->fillCallback !== null) {
            return Field::fill($request, $model, $requestAttribute);
        }

        $value = $this->lifecycle->parse($request, $requestAttribute ?? $this->attribute);

        if ($this->isNullable() && $this->isNullValue($value)) {
            $value = null;
        } elseif (is_string($value) && $value !== '') {
            $zone = app(Timezone::class)->forRequest($request);
            $value = Carbon::parse($value, $zone)->setTimezone(config()->string('app.timezone'));
        }

        return $this->lifecycle->persist($model, $this->attribute, $value);
    }

    public function schema(FieldContext $context, LatheRequest $request, ?Model $model = null): array
    {
        $value = $model === null ? $this->resolveDefaultValue($request) : $this->resolve($model);
        $moment = $value instanceof DateTimeInterface || (is_string($value) && $value !== '') ? Carbon::parse($value) : null;
        $zone = app(Timezone::class)->forRequest($request);

        return [
            ...parent::schema($context, $request, $model),
            'localValue' => $moment?->setTimezone($zone)->format(self::FORMAT),
        ];
    }

    /**
     * @return list<mixed>
     */
    protected function impliedRules(): array
    {
        return [
            'date',
            'regex:/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:\d{2})?\z/',
            ...array_slice(parent::impliedRules(), 1),
        ];
    }

    protected function assertCast(object $record): void
    {
        //
    }
}
