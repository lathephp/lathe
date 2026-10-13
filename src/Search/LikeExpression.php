<?php

namespace Lathe\Search;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Database\Query\Grammars\PostgresGrammar;

/**
 * `<column> LIKE ? ESCAPE '\'` for one qualified column, ILIKE on PostgreSQL. The column is
 * wrapped by the grammar of the query it lands in, and the term is always a binding.
 *
 * @internal
 */
class LikeExpression implements Expression
{
    public function __construct(
        protected string $column,
        protected bool $asText = false,
    ) {
        //
    }

    public function getValue(Grammar $grammar): string
    {
        $column = $grammar->wrap($this->column);
        $postgres = $grammar instanceof PostgresGrammar;

        // PostgreSQL has no LIKE on its uuid type, so a key is compared as text.
        if ($this->asText && $postgres) {
            $column = "cast({$column} as text)";
        }

        // MySQL and MariaDB read a backslash in a string literal as an escape, so the literal doubles it.
        $escape = $grammar instanceof MySqlGrammar ? "'\\\\'" : "'\\'";

        return $column.($postgres ? ' ilike' : ' like')." ? escape {$escape}";
    }
}
