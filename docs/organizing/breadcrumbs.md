---
title: Breadcrumbs
description: Show the trail above a record page, turn it off, and replace the trail of a Resource's detail page.
order: 4
---

# Breadcrumbs

A breadcrumb trail shows where a page sits in the panel and links back up. Lathe draws one above the detail, create, edit and replicate pages of a Resource. Dashboards and index pages have none.

## The default trail

The trail starts with the panel home and the Resource, then ends with the record:

| Page | Trail |
|------|-------|
| Detail | Dashboard / Orders / #12 |
| Edit | Dashboard / Orders / #12 / Edit |
| Create | Dashboard / Orders / Create Order |
| Replicate | Dashboard / Orders / #12 / Replicate |

"Dashboard" links to the panel home. The Resource label links to its index. The last crumb is plain text for the current page. The others are links.

A Resource that you declare as a child with `parent()` leads through its parent. Say `OrderItem` is a child of the `items` relation of `Order`:

```php
protected function configure(ResourceDefinition $definition): ResourceDefinition
{
    return $definition
        ->model(\App\Models\OrderItem::class)
        ->parent(Order::class, 'items');
}
```

An item's detail page then shows Dashboard / Orders / #12 / Items / #3, where `#12` is the parent order.

## Turn breadcrumbs off

Set `breadcrumbs.enabled` to `false` in `config/lathe.php`. The built-in trails then stay empty and the panel draws no trail:

```php
'breadcrumbs' => [
    'enabled' => false,
],
```

## Replace the trail of a detail page

Override `detailBreadcrumbs()` on a Resource to give its detail page another trail. Return a list of crumbs. Each crumb is an array with a `label` and an `href`. An `href` of `null` makes the crumb plain text, which is right for the current page:

```php
use Lathe\Http\Requests\LatheRequest;

public function detailBreadcrumbs(LatheRequest $request): ?array
{
    return [
        ['label' => 'Home', 'href' => '/lathe'],
        ['label' => 'All orders', 'href' => '/lathe/resources/orders'],
        ['label' => $this->title(), 'href' => null],
    ];
}
```

Return `null` to keep the default trail. Lathe builds the Resource around the record before it calls this method, so `$this->title()` and `$this->model` refer to the record on the page. That lets you lead through a related record:

```php
public function detailBreadcrumbs(LatheRequest $request): ?array
{
    $post = $this->model->post;

    return [
        ['label' => 'Dashboard', 'href' => '/lathe'],
        ['label' => 'Posts', 'href' => '/lathe/resources/posts'],
        ['label' => $post->title, 'href' => '/lathe/resources/posts/'.$post->getKey()],
        ['label' => $this->title(), 'href' => null],
    ];
}
```

> Note: The `href` values are URLs you write yourself, so they include the panel path. If you move the panel with the `path` key in `config/lathe.php`, update them. A trail that you return is sent as you build it, even when `breadcrumbs.enabled` is `false`, so check the setting yourself if the trail should follow it.

Only the detail page has this hook. The create, edit and replicate pages always use the default trail.

## What you see

Open a record in a Resource and a small trail sits above its title, with slashes between the crumbs. The earlier crumbs are links that take you back up to the panel home or the Resource index. The last crumb is plain text that names the page you are on. With `breadcrumbs.enabled` set to `false`, the trail is gone.
