---
title: Search
description: Declare the columns a Resource searches, choose how each column matches, and add global search and search providers.
order: 1
---

# Search

Lathe has two searches. The search box above a Resource's table filters that table. Global search, opened with <kbd>Ctrl</kbd>+<kbd>K</kbd> (<kbd>Cmd</kbd>+<kbd>K</kbd> on a Mac), looks across Resources and other sources from any page.

Both read the same declaration: the columns a Resource says it searches.

## Declare the searchable columns

Call `searchable()` on the definition in `configure()`. The table shows its search box once a Resource declares at least one column, or uses Scout.

```php
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;

class User extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition
            ->model(\App\Models\User::class)
            ->title('name')
            ->searchable(['name', 'email']);
    }
}
```

The `#[Search]` attribute does the same. It takes one array:

```php
use Lathe\Attributes\Resource\Search;

#[Search(['name', 'email'])]
class User extends Resource
{
    // ...
}
```

A fluent call in `configure()` wins over an attribute.

A bare string matches the start of the column: `name` with the term `ann` becomes `name LIKE 'ann%'`. A prefix match can use an index, so it stays fast on a large table. On PostgreSQL Lathe uses `ILIKE`, so the match ignores case there too.

Lathe escapes `%`, `_` and `\` in the term, so a user who types `%` matches a literal percent sign and not every row.

> Note: To match anywhere in a column, opt in per column with `LikeSearch::contains()`. No index helps a match in the middle of a value. `php artisan lathe:doctor` warns about every `contains` column on a table of more than 100,000 rows.

```php
use Lathe\Search\LikeSearch;

->searchable(['name', LikeSearch::contains('email')])
```

### The primary key

Once a Resource declares one column, Lathe adds the primary key. An integer key matches a term made only of digits, so `12` finds the record with id 12. A UUID, ULID or string key matches by prefix.

A digit term larger than the key column can hold would make PostgreSQL raise an overflow error. Lathe compares the digits as text and skips the key match when the term is larger than the maximum. The default maximum is `PHP_INT_MAX`. Lower it for a smaller column, such as a 4-byte integer:

```php
->maxPrimaryKeySize(2147483647)
```

The attribute form is `#[MaxPrimaryKeySize(2147483647)]` from `Lathe\Attributes\Resource\MaxPrimaryKeySize`.

### Limits on the term

Lathe trims the term and collapses runs of spaces to one. A term longer than `limits.search_term_chars` (100) characters, or with more than `limits.search_term_words` (8) words, answers 422 on `q`. A Resource sets its own caps with `searchTermChars()` and `searchTermWords()`. See [Configuration](../getting-started/configuration.md).

## Choose how a column matches

Each class in `Lathe\Search` is one strategy. Pass a mix of them to `searchable()`.

| Strategy | Matches |
|---|---|
| `'name'` or `LikeSearch::prefix('name')` | The start of the value, `term%`. |
| `LikeSearch::contains('name')` | Anywhere in the value, `%term%`. |
| `new SearchableExact('sku')` | The whole value, `= term`. Use it for SKUs, order numbers and codes. |
| `new SearchableText('body')` | Full text on MySQL, MariaDB and PostgreSQL. SQLite has no full text, so it matches anywhere in the column. |
| `new SearchableJson('meta->address->city')` | A JSON path, prefix by default. |
| `new SearchableRelation('author', 'name')` | A column of a related model. |
| `new SearchableMorphToRelation('commentable', 'title', [Post::class])` | A column of a `morphTo` parent. |

```php
use Lathe\Search\SearchableExact;
use Lathe\Search\SearchableRelation;
use Lathe\Search\SearchableText;

->searchable([
    'title',
    new SearchableExact('sku'),
    new SearchableText('body'),
    new SearchableRelation('author', 'name'),
])
```

A dotted string such as `'author.name'` is a column name, not a relation. Use `SearchableRelation` to search a relation. A relation the model does not define, or a column its table lacks, throws an `InvalidSearchColumn` error when Lathe builds the Resource, and the message names the fix.

### Full text

`SearchableText` runs `MATCH ... AGAINST` in boolean mode on MySQL and MariaDB, and `to_tsvector @@ plainto_tsquery` on PostgreSQL. It needs a full-text index. Lathe turns the characters `+ - < > ( ) ~ * " @` into spaces first, so a stray operator in the term never causes a syntax error. A second argument holds options for `whereFullText`, such as `language` on PostgreSQL:

```php
new SearchableText('body', ['language' => 'english'])
```

### Relations

`SearchableRelation` and `SearchableMorphToRelation` take a last argument, `mode`, which is `'prefix'` (the default) or `'contains'`. For a `belongsTo` relation, Lathe first reads the matching related keys (up to `limits.id_list_max`) and filters on the foreign key. That keeps the indexes of a large table in use. Past the limit, or for any other relation, it uses an `EXISTS` subquery.

`SearchableMorphToRelation` takes a list of morph types. An empty list searches every type stored in the table.

```php
new SearchableMorphToRelation('commentable', 'title')
new SearchableRelation('author', 'name', 'contains')
```

## Global search

Global search finds records in every Resource that opts in. Mark a Resource with `globallySearchable()`, and give it the columns and a title:

```php
protected function configure(ResourceDefinition $definition): ResourceDefinition
{
    return $definition
        ->model(\App\Models\Customer::class)
        ->title('name')
        ->subtitle('email')
        ->searchable(['name', LikeSearch::contains('email')])
        ->globallySearchable();
}
```

The attribute is `#[GloballySearchable]`. Pass `false` to switch it off again: `#[GloballySearchable(false)]`.

Global search needs the `globalSearch` ability. An app that never defines it keeps the palette closed. Define it in your service provider:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('globalSearch', fn ($user) => true);
```

See [Policies](../security/policies.md) for how Lathe checks abilities.

Lathe searches the Resources in the sidebar's order, skips any the user may not see, and returns at most 100 rows in one request. If a Resource's query fails, the other groups still return, and the palette says some results are missing.

### Title, subtitle and avatar

Each result shows the Resource's title and, below it, its subtitle. Set them on the definition with an attribute name or a closure that receives the model:

```php
->title('number')
->subtitle(fn (Order $order) => 'Customer: '.$order->customer->name)
```

The attributes `#[Title('number')]` and `#[Subtitle('email')]` do the same for a plain column.

If the subtitle reads a related model, load the relation with `->with(['customer'])` on the definition to avoid one query per result.

To show a different line in the palette only, leaving the record pages' subtitle as it is, use `globalSearchSubtitle()`. It takes an attribute name or a closure, like `subtitle()`:

```php
->globalSearchSubtitle(fn (Order $order) => $order->customer->name.' · '.$order->placed_at->format('M j, Y'))
```

A `Cover` field on the detail page supplies the avatar. A record without a picture gets the initials of its title.

### How many results

Each Resource returns up to `global_search.results` rows (5 by default). Change it for one Resource:

```php
->globalSearchResults(10)
```

or with `#[GlobalSearchResults(10)]`. When a Resource has more matches than it shows, the group ends with "Show all 20 orders", which opens the Resource's index searched for the term. The number is the index's count, capped the same way; a Resource that counts nothing (`CountStrategy::None`) shows no link.

The other `global_search` keys are `enabled` and `debounce`; both are in [Configuration](../getting-started/configuration.md). Set `global_search.enabled` to `false` to remove the palette.

### Scope global search

Global search runs the Resource's `searchQuery()`, which calls `indexQuery()` by default. A Resource that hides rows on its index hides them in search too. Override `searchQuery()` when the two should differ:

```php
use Illuminate\Database\Eloquent\Builder;
use Lathe\Http\Requests\LatheRequest;

public static function searchQuery(LatheRequest $request, Builder $query): Builder
{
    // Find archived items too, but keep the vendor boundary.
    return $query->where('vendor_id', $request->user()->id);
}
```

> Note: An override replaces the `indexQuery()` scope. Apply it again, or call `static::indexQuery($request, $query)` and narrow from there, on any Resource that restricts rows.

## Search providers

A search provider adds results that are not Resources, such as help pages or an external service. Extend `Lathe\Search\SearchProvider` and implement its four methods:

```php
use Lathe\Http\Requests\LatheRequest;
use Lathe\Search\GlobalSearchResult;
use Lathe\Search\SearchProvider;

class HelpArticles extends SearchProvider
{
    public function title(): string
    {
        return 'Help';
    }

    public function uriKey(): string
    {
        return 'help';
    }

    public function authorize(LatheRequest $request): bool
    {
        return true;
    }

    public function search(LatheRequest $request): array
    {
        $term = $request->string('q')->toString();

        return collect($this->lookup($term))
            ->map(fn (array $article) => GlobalSearchResult::make($article['title'])
                ->subtitle($article['section'])
                ->url('/pages/help/'.$article['slug'])
                ->id($article['slug']))
            ->all();
    }
}
```

`title()` is the heading of the group in the palette and `uriKey()` is its key. A provider that fails `authorize()` is skipped. Lathe keeps at most `global_search.results` rows per provider.

Build each row with `GlobalSearchResult::make()`:

| Method | Effect |
|---|---|
| `make(string $title)` | The row's title. |
| `subtitle(string $subtitle)` | Smaller text under the title. |
| `url(string $url)` | A path inside the panel. The palette adds the panel path. A row without a URL is dropped. |
| `avatar(string $avatar)` | A leading image URL. |
| `rounded(bool $rounded = true)` | Draws the avatar as a circle. |
| `id(string $id)` | A stable id. It defaults to a hash of the title. |

Register providers in the `boot()` method of your Lathe service provider, after `parent::boot()`:

```php
use Lathe\Lathe;

public function boot(): void
{
    parent::boot();

    Lathe::registerSearchProviders(new HelpArticles);
}
```

`Lathe::searchProviders()` returns the `Lathe\Search\GlobalSearch` registry that holds them, and `register()` on it does what `registerSearchProviders()` does.

## Scout

When the model uses `Laravel\Scout\Searchable`, Lathe searches that Resource through Scout. You do not configure anything else. Lathe passes the search to the Scout builder, keeps the matching keys, and applies filters, tabs, sorting and paging on top of them.

The columns you declare with `searchable()` are ignored while Scout is on. Your Scout engine decides what is searchable.

Narrow the Scout search by overriding `scoutQuery()` on the Resource:

```php
public static function scoutQuery(LatheRequest $request, mixed $query): mixed
{
    return $query->where('published', true);
}
```

Scout returns at most 200 keys. Change the cap with `scoutSearchResults()` or `#[ScoutSearchResults(100)]`. Force Scout on or off, whatever the model uses, with `useScout()` or `#[UseScout(false)]`. Forcing it on for a model without the trait fails when Lathe builds the Resource manifest.

## What you see

Above the table, a search box finds rows as you type, and a typed `%` matches only a literal percent sign. Press <kbd>/</kbd> to move focus to it. The palette opens with <kbd>Ctrl</kbd>+<kbd>K</kbd> and lists results in groups, one per Resource and one per provider, each with a title, a subtitle and an avatar. Pressing Enter opens the highlighted record. A term with no match shows a "No results" message.
