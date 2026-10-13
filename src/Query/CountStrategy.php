<?php

namespace Lathe\Query;

/**
 * How an index counts its rows.
 */
enum CountStrategy: string
{
    case Capped = 'capped';
    case Exact = 'exact';
    case Estimated = 'estimated';
    case None = 'none';
}
