<?php

namespace Lathe\Auth;

use Closure;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;

/**
 * Decides who may open the panel: a user on the panel guard who passes Lathe::auth(), else viewLathe.
 */
#[Singleton]
class PanelGate
{
    /**
     * @var (Closure(Request): mixed)|null
     */
    protected ?Closure $callback = null;

    public function __construct(protected AuthFactory $auth, protected Gate $gate, protected FortifyConfig $fortify)
    {
        //
    }

    /**
     * Replace the viewLathe gate check with a callback.
     *
     * @param  Closure(Request): mixed  $callback
     */
    public function using(Closure $callback): void
    {
        $this->callback = $callback;
    }

    public function check(Request $request): bool
    {
        $user = $this->auth->guard($this->fortify->guard())->user();

        if ($user === null) {
            return false;
        }

        if ($this->callback !== null) {
            return ($this->callback)($request) === true;
        }

        return $this->gate->forUser($user)->check('viewLathe');
    }
}
