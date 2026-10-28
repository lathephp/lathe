---
title: Limits and performance
description: The default limits that keep every page fast on tables with millions of rows, and the budgets Lathe's CI holds it to.
order: 10
---

# Limits and performance

Lathe is built to stay fast on a table with 10 million rows. It does that with limits: a list never counts every row, never shows every row, and never runs a large bulk action inside one request. Each limit is a key in `config/lathe.php`, and a Resource can set its own value in its definition.

## Default limits

Every limit is enforced on the server, not only in the browser.

| Limit | Default | Config key | Per Resource |
|---|---|---|---|
| Row count | Counts at most 10,001 rows and shows "10,000+". Pages past row 10,000 answer 422. | `limits.count_cap` | `countStrategy()` |
| Pagination type | Numbered pages | `pagination.type` | `paginationType()` |
| Page size | 25, with 25, 50 and 100 to choose from. At most 100. There is no "show all". | `pagination.per_page`, `pagination.per_page_options`, `limits.max_per_page` | `perPage()` |
| Relation picker | 25 results, searched on the server | `limits.picker_results` | `pickerResults()` |
| Search | Matches the start of a column (`term%`); "contains" is opt-in per column. At most 100 characters and 8 words. | `limits.search_term_chars`, `limits.search_term_words` | `searchable()`, `searchTermChars()`, `searchTermWords()` |
| Filter options | More than 100 options load as you type | `limits.filter_remote_threshold` | `filterRemoteThreshold()` |
| Bulk actions | 500 rows or fewer run at once; more run as a queued batch with progress and cancel | `limits.bulk_inline_max` | `bulkInlineMax()` |
| Ids in one request | 1,000 | `limits.id_list_max` | `idListMax()` |
| Widget values | Cached 60 seconds, flushed when the table is written | `limits.metrics_ttl` | `cacheFor()` on the widget |
| Global search | 5 results per Resource | `global_search.results` | `globalSearchResults()` |
| Saved tabs | 10 per user and Resource | `saved_tabs.max_per_resource` | none |
| Uploads and imports | 10 MB per file, 5 MB per image, 50 MB per import | `uploads.max_file_size_mb`, `uploads.max_image_size_mb`, `imports.max_file_size_mb` | `maxSize()` on the field |
| Export and import chunks | 500 rows | `exports.chunk_size`, `imports.chunk_size` | none |

`countStrategy()` takes a `Lathe\Query\CountStrategy`: `Capped` (the default), `Exact`, `Estimated` (the database's own estimate) or `None` (no total, only next and previous). Use `Exact` only on a table you know stays small.

```php
use Lathe\Query\CountStrategy;
use Lathe\Resources\ResourceDefinition;

protected function configure(ResourceDefinition $definition): ResourceDefinition
{
    return $definition
        ->model(\App\Models\Order::class)
        ->countStrategy(CountStrategy::Capped, 50_000)
        ->bulkInlineMax(200);
}
```

## Speed budgets

Lathe's CI fails a change that breaks one of these budgets, on SQLite, MySQL, MariaDB and PostgreSQL.

| Budget | Limit |
|---|---|
| List page queries | The same number for 100 rows as for 10 |
| JavaScript for the first page load | 200 KB gzipped |
| Resources built for one request, with 500 registered | Only the one the request uses |

## Keeping your own Resources fast

- Sort and search on indexed columns. A search that "contains" a term cannot use an index; `lathe:doctor` warns about it on large tables.
- Keep relation columns on the index to `belongsTo` fields; Lathe loads them in one query per relation.
- Index a Board's field together with each column users sort by, such as `(status, total_cents)`: every Board column reads its cards and its capped count through that field.
- Run a queue worker in production, so large bulk actions, exports and imports run in the background (see [Deployment](deployment.md)).

## What you see

Open a list on a table with more than 10,000 rows: the footer reads "Showing 1 to 25 of 10,000+", and the page answers as fast as it does on a small table.
