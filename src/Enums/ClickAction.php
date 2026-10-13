<?php

namespace Lathe\Enums;

/**
 * What a click on an index row opens.
 */
enum ClickAction: string
{
    case Detail = 'detail';
    case Edit = 'edit';
    case Select = 'select';
    case Preview = 'preview';
    case Ignore = 'ignore';
}
