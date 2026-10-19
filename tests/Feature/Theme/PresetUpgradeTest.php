<?php

use Illuminate\Support\Facades\Gate;
use Lathe\Theme\Color;
use Workbench\App\Models\User;

it('keeps the primary colour of a theme lathe:preset wrote on 1.0.0-beta.2', function () {
    $this->artisan('migrate')->assertSuccessful();
    Gate::define('viewLathe', fn () => true);
    $admin = User::forceCreate(['name' => 'Admin User', 'email' => 'admin@lathephp.com', 'password' => bcrypt('password')]);

    // 1.0.0-beta.2 knew 40 tokens, so its lathe:preset wrote those keys and no others.
    $new = ['brand', 'focus', 'skeleton', 'scrim', 'chart-other', 'sidebar-hover'];
    foreach (['primary', 'success', 'warning', 'info', 'destructive'] as $tone) {
        array_push($new, "{$tone}-subtle", "{$tone}-subtle-foreground");
    }
    $vars = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/shadcn/preset-a5ONTE.json'), true)['cssVars'];
    $colors = [];
    foreach (Color::cases() as $color) {
        if (! in_array($color->value, $new, true)) {
            $light = $vars['light'][$color->value] ?? null;
            $colors[$color->value] = $light === null ? null : ['light' => $light, 'dark' => $vars['dark'][$color->value]];
        }
    }
    config(['lathe.theme.colors' => $colors]);

    $html = (string) $this->actingAs($admin)->get('/lathe')->assertOk()->getContent();

    // The theme's style tag follows every stylesheet, so the same selector in it wins.
    preg_match('/<style\b[^>]*>(.*?)<\/style>/s', $html, $style, PREG_OFFSET_CAPTURE);
    preg_match_all('/<link rel="stylesheet"/', $html, $links, PREG_OFFSET_CAPTURE);
    preg_match('/:root\{[^}]*--primary:([^;}]+)/', $style[1][0], $light);
    preg_match('/\.dark\{[^}]*--primary:([^;}]+)/', $style[1][0], $dark);

    expect($colors)->toHaveCount(40)
        ->and($light[1] ?? null)->toBe('oklch(0.491 0.27 292.581)')
        ->and($dark[1] ?? null)->toBe('oklch(0.432 0.232 292.759)')
        ->and($links[0])->not->toBeEmpty()
        ->and(end($links[0])[1])->toBeLessThan($style[0][1]);
});
