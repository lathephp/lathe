<?php

namespace Lathe\Field;

/**
 * Where a field is shown: a list cell, the detail page, a form, the preview modal or a peek card.
 */
enum FieldContext: string
{
    case Index = 'index';
    case Detail = 'detail';
    case Create = 'create';
    case Update = 'update';
    case Preview = 'preview';
    case Peeking = 'peeking';

    public function isForm(): bool
    {
        return $this === self::Create || $this === self::Update;
    }
}
