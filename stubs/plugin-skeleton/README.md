# {{ title }}

A Lathe plugin.

## Installation

```bash
composer require {{ name }}
```

Lathe finds the plugin through `extra.lathe.plugin` in `composer.json`, so it needs no registration. Its page shows in the sidebar and in the command palette.

## Build the page

```bash
pnpm install
pnpm build
```

The build writes `dist/{{ package }}.js`. Commit `dist/` so the bundle ships with the package.

## Testing

```bash
composer test
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
