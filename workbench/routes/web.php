<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Lathe\Http\Middleware\EnsureJson;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Query\ResourceQuery;
use Lathe\Support\Timezone;
use Workbench\App\Lathe\Resources\Post;

Route::get('/', function () {
    return view('welcome');
});

// The two routes of the 500-Resource overhead budget run the same one indexed count:
// one through the panel's own middleware, one through a plain Laravel stack.
$count = fn () => ['count' => DB::table('orders')->whereBetween('id', [1, 1000])->count()];

Route::middleware(['web', 'auth'])->get('/bench/plain', $count);

Route::middleware(['lathe.api', EnsureJson::class])->get('/lathe/api/bench/count', $count);

// The Octane lane calls this after a request that changed config and timezone, to see
// that the worker is back to the defaults and the full Post scope.
Route::middleware(['web', 'auth'])->get('/bench/probe', fn (Request $request, Timezone $timezone) => [
    'striped' => config('lathe.table.striped'),
    'fortify' => config('fortify'),
    'timezone' => $timezone->forRequest($request),
    'scope' => (new ResourceQuery(Post::make(), LatheRequest::createFrom($request)))->scoped()->toBase()->count(),
]);
