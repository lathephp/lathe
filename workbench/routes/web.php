<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Lathe\Http\Middleware\EnsureJson;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\ResourceQuery;
use Lathe\Support\Timezone;
use Workbench\App\Lathe\Resources\Post;

Route::get('/', function () {
    return view('welcome');
});

// The plain side of the 500-Resource overhead budget: the unread count the bell polls,
// GET /lathe/api/notifications/count, as one query through a plain Laravel stack.
Route::middleware(['web', 'auth'])->get('/bench/plain', fn (Request $request) => ['count' => DB::table('notifications')
    ->where('notifiable_type', $request->user()?->getMorphClass())
    ->where('notifiable_id', $request->user()?->getAuthIdentifier())
    ->whereNull('read_at')
    ->count()]);

// The Octane lane calls this after a request that changed config and timezone, to see
// that the worker is back to the defaults and the full Post scope.
Route::middleware(['web', 'auth'])->get('/bench/probe', fn (Request $request, Timezone $timezone) => [
    'striped' => config('lathe.table.striped'),
    'fortify' => config('fortify'),
    'timezone' => $timezone->forRequest($request),
    'scope' => (new ResourceQuery(Post::make(), LatheRequest::createFrom($request)))->scoped()->toBase()->count(),
]);

// The table render budget: 100 rows of 20 text columns through the production index page.
// Each ?run= gives new row ids and text, so the browser bench mounts fresh rows every run.
Route::middleware('lathe.api')->get('/lathe/bench/table', function (Request $request) {
    $run = $request->integer('run');
    $keys = array_map(fn (int $column) => "column_{$column}", range(1, 20));

    return Inertia::render('Resources/Index', [
        'resource' => [
            'uriKey' => 'bench',
            'label' => 'Bench rows',
            'singularLabel' => 'Bench row',
            'perPageOptions' => [100],
            'searchable' => false,
            'softDeletes' => false,
            'abilities' => ['create' => false],
            'definition' => [
                'debounce' => 300,
                'paginationType' => 'simple',
                'clickAction' => 'ignore',
                'polling' => null,
                'showPollingToggle' => false,
                'tableStyle' => 'default',
                'showColumnBorders' => false,
                'softDeletes' => false,
            ],
        ],
        'table' => [
            'columns' => array_map(fn (string $key, int $index) => [
                'key' => $key,
                'label' => 'Column '.($index + 1),
                'component' => 'text',
                'sortable' => false,
                'sortKey' => $key,
            ], $keys, array_keys($keys)),
            'sortKeys' => [],
            'rows' => array_map(fn (int $row) => [
                'id' => $run * 100 + $row,
                'title' => "Row {$row}",
                'cells' => array_combine($keys, array_map(
                    fn (int $column) => ['value' => "r{$run}-{$row}-{$column}", 'display' => "r{$run}-{$row}-{$column}"],
                    range(1, 20),
                )),
                'abilities' => [],
            ], range(1, 100)),
            'rowKey' => 'id',
        ],
        'state' => ['page' => 1, 'perPage' => 100, 'hasMorePages' => false, 'sort' => ''],
        'lens' => null,
        'lenses' => [],
    ]);
});
