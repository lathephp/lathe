---
title: Status meters
description: Show a select field as a row of ticks that fill up as the value moves along its options.
order: 7
---

# Status meters

A status meter shows a select field as a row of ticks. A value fills the ticks up to its place, so a glance at the list tells how far each record has come.

Call `meter()` on an inline select:

```php
Field::select('Status')
    ->options(['pending' => 'Pending', 'paid' => 'Paid', 'shipped' => 'Shipped', 'cancelled' => 'Cancelled'])
    ->meter(except: ['cancelled'])
    ->inline();
```

The options, in the order you declared them, less those in `except`, are the ticks. Here the ticks are pending, paid and shipped. A record that is paid fills two.

A value in `except` shows every tick empty. A cancelled order has not moved along the way, so its meter is blank.

Without `except`, every option is a tick:

```php
Field::select('Priority')->options(['low', 'medium', 'high'])->meter();
```

## Limits

A meter has 2 to 5 ticks. With fewer or more, `fields()` throws a `LogicException` that names the field. The options must be inline, not remote.

A meter also heads each column of the [Board](../querying/board.md) when the Board field has one.

The ticks and the label take the tone that `badges()` gives the value, so a meter and badges work together. Without a tone, they are neutral.

## What you see

The Status column shows a row of ticks with the option's label beside it. A pending order fills one of three, a shipped order fills all three, and a cancelled order shows three empty ticks.
