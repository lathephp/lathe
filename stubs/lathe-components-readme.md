# lathe-components/

Plugins that live in this project.

Each subdirectory is a Composer path repository registered in the project's
`composer.json` as `lathe-components/*`, and a pnpm workspace package listed in
`pnpm-workspace.yaml`.

## Creating a plugin

```bash
php artisan lathe:plugin acme/my-feature
```

## Generating files inside a plugin

```bash
php artisan lathe:resource Article --plugin=acme/my-feature
php artisan lathe:action Notify --plugin=acme/my-feature
```

The class lands in `lathe-components/MyFeature/src/{Resources,Actions,...}`
under the plugin's namespace.

## Building a plugin's page

```bash
cd lathe-components/MyFeature
pnpm install
pnpm build
```

## Graduating to Packagist

When the plugin is stable, move it out of the project:

1. `git subtree split --prefix=lathe-components/MyFeature -b extract`
2. Push that branch to its own repository.
3. Tag `v1.0.0` and publish it on Packagist, or on your private Composer repository.
4. In this project, delete the plugin's folder and run
   `composer require acme/my-feature:^1.0`.

After that the plugin lives in `vendor/`, and the project needs no code changes.
