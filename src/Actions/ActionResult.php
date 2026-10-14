<?php

namespace Lathe\Actions;

use InvalidArgumentException;
use JsonSerializable;
use Lathe\Lathe;
use stdClass;

/**
 * What an action answers: one optional toast message and at most one effect. Setting a
 * second effect replaces the first.
 *
 * @phpstan-type Effect array{type: 'redirect', url: string, newTab: bool}|array{type: 'visit', path: string, options: array<string, mixed>}|array{type: 'download', name: string, url: string}|array{type: 'modal', component: string, data: array<string, mixed>}|array{type: 'emit', event: string, data: array<string, mixed>}|array{type: 'deleted'}
 *
 * @phpstan-consistent-constructor
 */
class ActionResult implements JsonSerializable
{
    protected ?string $message = null;

    /** @var 'success'|'danger' */
    protected string $level = 'success';

    /** @var Effect|null */
    protected ?array $effect = null;

    public static function message(string $text): static
    {
        return (new static)->withMessage($text);
    }

    public static function danger(string $text): static
    {
        return (new static)->withDangerMessage($text);
    }

    public static function redirect(string $url, bool $newTab = false): static
    {
        return (new static)->withRedirect($url, $newTab);
    }

    public static function openInNewTab(string $url): static
    {
        return (new static)->withRedirect($url, true);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public static function visit(string $path, array $options = []): static
    {
        return (new static)->withVisitOptions($path, $options);
    }

    public static function download(string $name, string $url): static
    {
        return (new static)->withDownload($name, $url);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function modal(string $component, array $data = []): static
    {
        return (new static)->withModal($component, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function emit(string $event, array $data = []): static
    {
        return (new static)->withEvent($event, $data);
    }

    public static function deleted(): static
    {
        return (new static)->withMessage(Lathe::__('The resource was deleted.'))->withDeleted();
    }

    public function withMessage(string $text): static
    {
        $this->message = $text;
        $this->level = 'success';

        return $this;
    }

    public function withDangerMessage(string $text): static
    {
        $this->message = $text;
        $this->level = 'danger';

        return $this;
    }

    public function withRedirect(string $url, bool $newTab = false): static
    {
        $this->effect = ['type' => 'redirect', 'url' => static::safeUrl($url), 'newTab' => $newTab];

        return $this;
    }

    /**
     * Open the redirect in a new tab; without a redirect effect there is nothing to open.
     */
    public function usingNewTab(): static
    {
        if ($this->effect !== null && $this->effect['type'] === 'redirect') {
            $this->effect['newTab'] = true;
        }

        return $this;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function withVisitOptions(string $path, array $options = []): static
    {
        $this->effect = ['type' => 'visit', 'path' => static::safeUrl($path), 'options' => $options];

        return $this;
    }

    public function withDownload(string $name, string $url): static
    {
        $this->effect = ['type' => 'download', 'name' => $name, 'url' => static::safeUrl($url)];

        return $this;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function withModal(string $component, array $data = []): static
    {
        $this->effect = ['type' => 'modal', 'component' => $component, 'data' => $data];

        return $this;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function withEvent(string $event, array $data = []): static
    {
        $this->effect = ['type' => 'emit', 'event' => $event, 'data' => $data];

        return $this;
    }

    public function withDeleted(): static
    {
        $this->effect = ['type' => 'deleted'];

        return $this;
    }

    /**
     * A URL the browser may follow: relative, http or https. Browsers drop tabs and line
     * breaks inside a URL, so "java\tscript:" would run; control characters are refused.
     */
    protected static function safeUrl(string $url): string
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            throw new InvalidArgumentException('An action URL may not contain control characters.');
        }

        if (preg_match('/^\s*([a-z][a-z0-9+.\-]*):/i', $url, $match) === 1 && ! in_array(strtolower($match[1]), ['http', 'https'], true)) {
            throw new InvalidArgumentException("An action URL must be relative, http or https, \"{$match[1]}\" given.");
        }

        return $url;
    }

    /**
     * @return array{message: string|null, level: 'success'|'danger', effect: array<string, mixed>|null}
     */
    public function jsonSerialize(): array
    {
        $effect = $this->effect;

        // An empty PHP array encodes as [], and the client reads options and data as objects.
        foreach (['options', 'data'] as $key) {
            if (isset($effect[$key]) && $effect[$key] === []) {
                $effect[$key] = new stdClass;
            }
        }

        return ['message' => $this->message, 'level' => $this->level, 'effect' => $effect];
    }
}
