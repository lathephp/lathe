<?php

use Lathe\Theme\Color;

it('has one case per colour token in the config', function () {
    expect(array_map(fn (Color $color) => $color->value, Color::cases()))->toBe([
        'background', 'foreground', 'card', 'card-foreground', 'popover', 'popover-foreground',
        'primary', 'primary-foreground', 'secondary', 'secondary-foreground', 'muted', 'muted-foreground',
        'accent', 'accent-foreground', 'destructive', 'destructive-foreground', 'success', 'success-foreground',
        'warning', 'warning-foreground', 'info', 'info-foreground', 'border', 'input', 'ring', 'link', 'link-hover',
        'chart-1', 'chart-2', 'chart-3', 'chart-4', 'chart-5', 'sidebar', 'sidebar-foreground',
        'sidebar-primary', 'sidebar-primary-foreground', 'sidebar-accent', 'sidebar-accent-foreground',
        'sidebar-border', 'sidebar-ring',
    ])->and(Color::cases())->toHaveCount(40);
});

it('names the CSS variable and utility of a token', function () {
    expect(Color::Primary->variable())->toBe('--primary')
        ->and(Color::Primary->utility())->toBe('bg-primary')
        ->and(Color::SidebarAccentForeground->variable())->toBe('--sidebar-accent-foreground');
});
