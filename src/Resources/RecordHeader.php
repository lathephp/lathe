<?php

namespace Lathe\Resources;

use Lathe\Field\BadgeField;
use Lathe\Field\Field;
use Lathe\Field\SelectField;

/**
 * Which detail fields a record page's header shows: a badge beside the title (the first badge
 * unless the Resource names one with recordHeader()) and the meta line's field, shown as
 * "<Label> <value>" in place of the subtitle when the Resource names one.
 */
class RecordHeader
{
    /**
     * @param  list<Field>  $fields  the detail fields, in code order
     * @param  array{badge?: string|null, meta?: string|null}  $chosen
     * @return array{badge: string|null, meta: string|null}
     */
    public static function pick(array $fields, array $chosen): array
    {
        $keys = array_map(fn (Field $field) => $field->key(), $fields);
        $named = fn (string $slot): ?string => in_array($chosen[$slot] ?? null, $keys, true) ? $chosen[$slot] : null;
        $badge = null;

        foreach ($fields as $field) {
            if ($field instanceof BadgeField || ($field instanceof SelectField && $field->hasBadges())) {
                $badge = $field->key();
                break;
            }
        }

        return ['badge' => $named('badge') ?? $badge, 'meta' => $named('meta')];
    }
}
