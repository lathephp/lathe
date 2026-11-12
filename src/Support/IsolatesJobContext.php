<?php

namespace Lathe\Support;

use Closure;

/**
 * For a job that restores a JobContext: its middleware puts the worker's user, default guard
 * and locale back once the job ends or throws, so the next job never runs as this one's actor.
 *
 * @property-read JobContext $context
 */
trait IsolatesJobContext
{
    /**
     * @return list<Closure(object, Closure): mixed>
     */
    public function middleware(): array
    {
        return [fn (object $job, Closure $next): mixed => $this->context->isolate(fn (): mixed => $next($job))];
    }
}
