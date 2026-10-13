<?php

namespace Lathe\Enums;

/**
 * How an index pages its rows.
 */
enum PaginationType: string
{
    case Links = 'links';
    case Simple = 'simple';
    case LoadMore = 'load-more';
}
