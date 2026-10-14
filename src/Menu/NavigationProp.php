<?php

namespace Lathe\Menu;

use Closure;
use Inertia\OnceProp;

/**
 * The sidebar as an Inertia once prop keyed by the hash of its JSON. The client sends back
 * the keys it holds, so a visit whose sidebar did not change carries no navigation.
 */
class NavigationProp extends OnceProp
{
    /** @var list<array<string, mixed>>|null */
    protected ?array $navigation = null;

    /**
     * @param  Closure(): list<array<string, mixed>>  $build
     */
    public function __construct(protected Closure $build)
    {
        parent::__construct(fn () => $this->navigation());
    }

    /**
     * Inertia asks for the key before it resolves the value, so the key builds the sidebar.
     */
    public function getKey(): string
    {
        return 'navigation:'.hash('xxh128', (string) json_encode($this->navigation()));
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function navigation(): array
    {
        return $this->navigation ??= ($this->build)();
    }
}
