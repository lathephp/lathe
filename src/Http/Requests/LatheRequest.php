<?php

namespace Lathe\Http\Requests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Lathe\Lathe;
use Lathe\Query\Selection;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceRegistry;
use Lathe\Support\ConfigStore;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The current panel request, bound for the length of one request only.
 */
class LatheRequest extends Request
{
    /**
     * The user of the panel guard.
     *
     * @param  string|null  $guard
     */
    public function user($guard = null): ?Authenticatable
    {
        return auth()->guard($guard ?? $this->config()->nullableString('guard'))->user();
    }

    /**
     * The page size, clamped to 1..limits.max_per_page.
     */
    public function perPage(): int
    {
        $default = $this->config()->integer('pagination.per_page');
        $max = $this->config()->integer('limits.max_per_page');
        $value = $this->query('per_page');

        $perPage = is_string($value) && ctype_digit($value) ? (int) $value : $default;

        return max(1, min($perPage, $max));
    }

    /**
     * The sort direction: asc or desc, nothing else.
     */
    public function orderByDirection(): string
    {
        $value = $this->query('direction');

        return is_string($value) && strtolower($value) === 'desc' ? 'desc' : 'asc';
    }

    /**
     * Which trashed rows to include: '', 'with' or 'only'.
     */
    public function trashed(): string
    {
        $value = $this->query('trashed');

        return in_array($value, ['with', 'only'], true) ? $value : '';
    }

    /**
     * The search term, cut to limits.search_term_chars and limits.search_term_words.
     */
    public function searchKeyword(): string
    {
        $value = $this->query('search');

        if (! is_string($value)) {
            return '';
        }

        $words = preg_split('/\s+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_slice($words, 0, $this->config()->integer('limits.search_term_words'));

        return mb_substr(implode(' ', $words), 0, $this->config()->integer('limits.search_term_chars'));
    }

    /**
     * The `selection` field: an object, or its JSON text in a multipart body. 422 on the
     * `selection` key for a shape the server cannot read, so a half-read selection never
     * reaches a write.
     */
    public function selection(): Selection
    {
        $data = $this->input('selection');
        $data = is_string($data) ? json_decode($data, true) : $data;

        if (! is_array($data) || ($data !== [] && array_is_list($data))) {
            throw $this->invalidSelection(Lathe::__('The selection must be an object.'));
        }

        $mode = $data['mode'] ?? null;

        if ($mode !== 'ids' && $mode !== 'all') {
            throw $this->invalidSelection(Lathe::__('The selection mode must be ids or all.'));
        }

        foreach (['q', 'tab', 'saved_tab', 'trashed', 'lens', 'via'] as $key) {
            if (isset($data[$key]) && ! is_string($data[$key])) {
                throw $this->invalidSelection(Lathe::__('The selection must be an object.'));
            }
        }

        if (isset($data['filters']) && ! is_array($data['filters'])) {
            throw $this->invalidSelection(Lathe::__('The selection must be an object.'));
        }

        $ids = $this->selectionKeys($data['ids'] ?? []);
        $exclusions = $this->selectionKeys($data['exclusions'] ?? []);

        $max = $this->resourceOrFail()::make()->definition()->getIdListMax();

        if ($mode === 'ids' && count($ids) > $max) {
            throw $this->invalidSelection(Lathe::__('The selection may name at most :max ids.', ['max' => number_format($max)]));
        }

        if (count($exclusions) > 1000) {
            throw $this->invalidSelection(Lathe::__('The selection may leave out at most :max rows.', ['max' => '1,000']));
        }

        return Selection::fromArray($data);
    }

    /**
     * @return list<int|string>
     */
    protected function selectionKeys(mixed $keys): array
    {
        $list = is_array($keys) ? array_values(array_filter($keys, fn (mixed $key) => is_int($key) || is_string($key))) : null;

        return $list !== null && count($list) === count($keys) ? $list : throw $this->invalidSelection(Lathe::__('The selection keys must be numbers or text.'));
    }

    protected function invalidSelection(string $message): ValidationException
    {
        return ValidationException::withMessages(['selection' => $message]);
    }

    /**
     * The {resource} key of the current route.
     */
    public function resourceKey(): ?string
    {
        $key = $this->route('resource');

        return is_string($key) ? $key : null;
    }

    /**
     * The Resource class the route names, or null when none is registered under it.
     *
     * @return class-string<resource>|null
     */
    public function resource(): ?string
    {
        $key = $this->resourceKey();

        return $key === null ? null : app(ResourceRegistry::class)->forKey($key);
    }

    /**
     * The Resource class the route names; 404 when there is none.
     *
     * @return class-string<resource>
     */
    public function resourceOrFail(): string
    {
        return $this->resource() ?? throw new NotFoundHttpException;
    }

    /**
     * An instance of the Resource the route names, or null when none is registered under it.
     */
    public function resourceInstance(): ?Resource
    {
        $class = $this->resource();

        return $class === null ? null : $class::make();
    }

    /**
     * A new instance of the Resource the route names; 404 when there is none.
     */
    public function newResource(): Resource
    {
        return $this->resourceOrFail()::make();
    }

    protected function config(): ConfigStore
    {
        return app(ConfigStore::class);
    }
}
