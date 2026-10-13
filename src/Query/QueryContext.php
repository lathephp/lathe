<?php

namespace Lathe\Query;

/**
 * Where a Resource query is used; each context has its own Resource hook.
 */
enum QueryContext
{
    case Index;
    case Detail;
    case Edit;
    case Search;
    case Widget;
    case Relatable;
    case Replicate;
}
