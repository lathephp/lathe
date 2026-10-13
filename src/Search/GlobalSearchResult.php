<?php

namespace Lathe\Search;

/**
 * One row a SearchProvider returns to global search.
 */
class GlobalSearchResult
{
    protected ?string $subtitle = null;

    protected ?string $url = null;

    protected ?string $avatar = null;

    protected bool $rounded = false;

    protected ?string $id = null;

    final public function __construct(protected string $title)
    {
        //
    }

    public static function make(string $title): static
    {
        return new static($title);
    }

    public function subtitle(string $subtitle): static
    {
        $this->subtitle = $subtitle;

        return $this;
    }

    /**
     * A path inside the panel; the palette prefixes the panel path.
     */
    public function url(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function avatar(string $avatar): static
    {
        $this->avatar = $avatar;

        return $this;
    }

    public function rounded(bool $rounded = true): static
    {
        $this->rounded = $rounded;

        return $this;
    }

    public function id(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    /**
     * The row in the shape the palette reads, under the provider's group.
     *
     * @return array{resourceTitle: string, title: string, subTitle: string|null, resourceName: string, resourceId: string, avatar: string|null, rounded: bool, url: string|null}
     */
    public function toRow(SearchProvider $provider): array
    {
        return [
            'resourceTitle' => $provider->title(),
            'title' => $this->title,
            'subTitle' => $this->subtitle,
            'resourceName' => $provider->uriKey(),
            'resourceId' => $this->id ?? md5($this->title),
            'avatar' => $this->avatar,
            'rounded' => $this->rounded,
            'url' => $this->url,
        ];
    }
}
