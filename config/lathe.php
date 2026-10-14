<?php

use Lathe\Http\Middleware\Authenticate;
use Lathe\Http\Middleware\Authorize;
use Lathe\Http\Middleware\HandleInertiaRequests;

return [

    /*
    |--------------------------------------------------------------------------
    | Panel and routing
    |--------------------------------------------------------------------------
    |
    | The name shows in the sidebar and the page title. The version label shows
    | the installed version when null, a string of your choice, or nothing when
    | false. When a domain is set, every panel route answers only on that host.
    |
    */

    'name' => env('LATHE_APP_NAME', env('APP_NAME', 'Lathe')),

    'version' => null,

    'domain' => env('LATHE_DOMAIN_NAME'),

    'path' => '/lathe',

    'guard' => env('LATHE_GUARD'),

    'passwords' => env('LATHE_PASSWORDS'),

    'middleware' => ['web', HandleInertiaRequests::class],

    'api_middleware' => ['lathe', Authenticate::class, Authorize::class],

    'initial_path' => null,

    'currency' => 'USD',

    /*
    |--------------------------------------------------------------------------
    | Pagination, limits and uploads
    |--------------------------------------------------------------------------
    |
    | Every limit here is a default that a Resource definition may override.
    |
    */

    'pagination' => [
        'type' => 'links',
        'per_page' => 25,
        'per_page_options' => [25, 50, 100],
    ],

    'limits' => [
        'max_per_page' => 100,
        'count_cap' => 10000,
        'picker_results' => 25,
        'search_term_chars' => 100,
        'search_term_words' => 8,
        'filter_remote_threshold' => 100,
        'bulk_inline_max' => 500,
        'id_list_max' => 1000,
        'metrics_ttl' => 60,
        'repeater_rows' => 200,
        'key_value_rows' => 200,
        'key_length' => 255,
    ],

    'uploads' => [
        'max_file_size_mb' => 10,
        'max_image_size_mb' => 5,
        'chunk_size' => 5242880,
        'pending_hours' => 24,
    ],

    'storage_disk' => env('LATHE_STORAGE_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Search, notifications, saved tabs and other features
    |--------------------------------------------------------------------------
    */

    'global_search' => [
        'enabled' => true,
        'debounce' => 0.5,
        'results' => 5,
    ],

    'notifications' => [
        'enabled' => true,
        'polling_interval' => 7,
        'show_unread_count' => false,
    ],

    'saved_tabs' => [
        'enabled' => true,
        'max_per_resource' => 10,
    ],

    'keyboard_shortcuts' => [
        'enabled' => true,
    ],

    'breadcrumbs' => [
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Auth and users
    |--------------------------------------------------------------------------
    */

    'fortify' => [
        'features' => [],
        'username' => null,
        'email' => null,
    ],

    'impersonation' => [
        'started' => env('LATHE_IMPERSONATION_STARTED', '/'),
        'stopped' => env('LATHE_IMPERSONATION_STOPPED', '/'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Data lifecycle
    |--------------------------------------------------------------------------
    */

    'action_events' => [
        'enabled' => true,
        'prune_after_days' => 30,
        'prune_per_actionable' => null,
    ],

    'exports' => [
        'disk' => env('LATHE_EXPORT_DISK', 'local'),
        'directory' => 'lathe-exports',
        'chunk_size' => 500,
        'prune_after_hours' => 24,
    ],

    'imports' => [
        'disk' => env('LATHE_IMPORT_DISK', 'local'),
        'directory' => 'lathe-imports',
        'chunk_size' => 500,
        'max_file_size_mb' => 50,
        'prune_after_hours' => 24,
    ],

    /*
    |--------------------------------------------------------------------------
    | Appearance
    |--------------------------------------------------------------------------
    |
    | Each colour is a CSS colour string or ['light' => ..., 'dark' => ...];
    | null keeps the style's own value. The style is one of nova (the default),
    | vega, lyra, maia, mira, luma, rhea or sera.
    |
    */

    'theme' => [
        'colors' => [
            'background' => null,
            'foreground' => null,
            'card' => null,
            'card-foreground' => null,
            'popover' => null,
            'popover-foreground' => null,
            'primary' => null,
            'primary-foreground' => null,
            'secondary' => null,
            'secondary-foreground' => null,
            'muted' => null,
            'muted-foreground' => null,
            'accent' => null,
            'accent-foreground' => null,
            'destructive' => null,
            'destructive-foreground' => null,
            'success' => null,
            'success-foreground' => null,
            'warning' => null,
            'warning-foreground' => null,
            'info' => null,
            'info-foreground' => null,
            'border' => null,
            'input' => null,
            'ring' => null,
            'link' => null,
            'link-hover' => null,
            'chart-1' => null,
            'chart-2' => null,
            'chart-3' => null,
            'chart-4' => null,
            'chart-5' => null,
            'sidebar' => null,
            'sidebar-foreground' => null,
            'sidebar-primary' => null,
            'sidebar-primary-foreground' => null,
            'sidebar-accent' => null,
            'sidebar-accent-foreground' => null,
            'sidebar-border' => null,
            'sidebar-ring' => null,
        ],
        'appearance' => 'system',
        'radius' => '0.625rem',
        'style' => 'nova',
        // null uses the bundled Inter Variable. To use your own font, set
        // ['family' => 'Acme Sans', 'fallback' => 'sans-serif', 'files' => [['url' => '/fonts/acme.woff2', 'weight' => 400, 'style' => 'normal']]],
        // with the files under your public folder. A family without files must be a system font stack.
        'font' => null,
    ],

    'layout' => [
        'sidebar' => [
            'collapsible' => 'icon',
            'default_open' => true,
            'width' => '16rem',
            'width_icon' => '3rem',
        ],
        'header' => [
            'sticky' => true,
            'search' => true,
        ],
        'content' => [
            'width' => 'full',
        ],
    ],

    'table' => [
        'density' => 'comfortable',
        'striped' => false,
    ],

];
