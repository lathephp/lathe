<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Lathe\Http\Middleware\EnsureJson;

Route::get('/', function () {
    return view('welcome');
});

// The two routes of the 500-Resource overhead budget run the same one indexed count:
// one through the panel's own middleware, one through a plain Laravel stack.
$count = fn () => ['count' => DB::table('orders')->whereBetween('id', [1, 1000])->count()];

Route::middleware(['web', 'auth'])->get('/bench/plain', $count);

Route::middleware(['lathe.api', EnsureJson::class])->get('/lathe/api/bench/count', $count);
