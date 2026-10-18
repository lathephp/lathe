<?php

namespace Lathe\Resources;

use Lathe\Field\BadgeField;
use Lathe\Field\Field;
use Lathe\Field\NumericField;
use Lathe\Field\Relations\BelongsToField;
use Lathe\Field\Relations\MorphToField;
use Lathe\Field\SelectField;

/**
 * Which index columns a row's card shows below 768 px: its title (null for the record's
 * title), its first badge, its first money or number and its first relation. A slot the
 * Resource names with phoneCard() takes that column when the index shows it.
 */
class PhoneCard
{
    /**
     * @param  list<Field>  $fields  the index columns, in code order
     * @param  array{title?: string|null, badge?: string|null, value?: string|null, meta?: string|null}  $chosen
     * @return array{title: string|null, badge: string|null, value: string|null, meta: string|null}
     */
    public static function pick(array $fields, array $chosen): array
    {
        $keys = array_map(fn (Field $field) => $field->key(), $fields);
        $first = function (callable $matches) use ($fields): ?string {
            foreach ($fields as $field) {
                if ($matches($field)) {
                    return $field->key();
                }
            }

            return null;
        };
        $named = fn (string $slot): ?string => in_array($chosen[$slot] ?? null, $keys, true) ? $chosen[$slot] : null;

        return [
            'title' => $named('title'),
            'badge' => $named('badge') ?? $first(fn (Field $field) => $field instanceof BadgeField || ($field instanceof SelectField && $field->hasBadges())),
            'value' => $named('value') ?? $first(fn (Field $field) => $field instanceof NumericField),
            'meta' => $named('meta') ?? $first(fn (Field $field) => $field instanceof BelongsToField || $field instanceof MorphToField),
        ];
    }
}
