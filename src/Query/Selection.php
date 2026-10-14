<?php

namespace Lathe\Query;

/**
 * The rows an action or a batch write names: a list of keys, or the whole index state
 * minus the rows the user unticked. It holds the request body as sent; SelectionResolver
 * turns it into rows.
 */
final class Selection
{
    /**
     * @param  'ids'|'all'  $mode
     * @param  list<int|string>  $ids
     * @param  list<int|string>  $exclusions
     * @param  array<array-key, mixed>  $filters  the filter[key] shapes of the index URL
     */
    public function __construct(
        public readonly string $mode,
        public readonly array $ids = [],
        public readonly array $exclusions = [],
        public readonly ?string $q = null,
        public readonly array $filters = [],
        public readonly ?string $tab = null,
        public readonly ?string $savedTab = null,
        public readonly ?string $trashed = null,
        public readonly ?string $lens = null,
        public readonly ?string $via = null,
    ) {
        //
    }

    public function isAll(): bool
    {
        return $this->mode === 'all';
    }

    /**
     * @param  array<array-key, mixed>  $data  the body keys, snake_case
     */
    public static function fromArray(array $data): self
    {
        return new self(
            mode: ($data['mode'] ?? null) === 'all' ? 'all' : 'ids',
            ids: self::keys($data['ids'] ?? []),
            exclusions: self::keys($data['exclusions'] ?? []),
            q: self::text($data['q'] ?? null),
            filters: is_array($data['filters'] ?? null) ? $data['filters'] : [],
            tab: self::text($data['tab'] ?? null),
            savedTab: self::text($data['saved_tab'] ?? null),
            trashed: self::text($data['trashed'] ?? null),
            lens: self::text($data['lens'] ?? null),
            via: self::text($data['via'] ?? null),
        );
    }

    /**
     * The ten body keys, as a queued payload keeps them.
     *
     * @return array{mode: string, ids: list<int|string>, exclusions: list<int|string>, q: ?string, filters: array<array-key, mixed>, tab: ?string, saved_tab: ?string, trashed: ?string, lens: ?string, via: ?string}
     */
    public function toArray(): array
    {
        return [
            'mode' => $this->mode,
            'ids' => $this->ids,
            'exclusions' => $this->exclusions,
            'q' => $this->q,
            'filters' => $this->filters,
            'tab' => $this->tab,
            'saved_tab' => $this->savedTab,
            'trashed' => $this->trashed,
            'lens' => $this->lens,
            'via' => $this->via,
        ];
    }

    /**
     * @return list<int|string>
     */
    private static function keys(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, fn (mixed $key) => is_int($key) || is_string($key))) : [];
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
