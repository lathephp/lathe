<?php

namespace Lathe\Tests\Fixtures\Plugins;

use Closure;
use Illuminate\Http\Request;
use Lathe\Field\Field;
use Lathe\Plugins\Plugin;
use Lathe\Tests\Fixtures\Resources\Gadget;
use Symfony\Component\HttpFoundation\Response;

/**
 * A plugin with Resources, middleware and settings, and a setter to prove a configured
 * instance survives discovery.
 */
class Toolbox extends Plugin
{
    public string $flavour = 'plain';

    public bool $withSettings = true;

    public function flavour(string $flavour): static
    {
        $this->flavour = $flavour;

        return $this;
    }

    public function resources(): array
    {
        return [Gadget::class];
    }

    public function middleware(): array
    {
        return [ToolboxHeader::class];
    }

    public function settings(): ?array
    {
        return $this->withSettings ? [Field::make('Greeting')] : null;
    }
}

/**
 * Stamps every panel response, to show a plugin's middleware joins the lathe group.
 */
class ToolboxHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Toolbox', 'on');

        return $response;
    }
}
