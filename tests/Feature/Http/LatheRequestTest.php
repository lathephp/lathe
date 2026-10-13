<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Lathe\Http\Requests\LatheRequest;

function latheRequestFor(string $query): LatheRequest
{
    return LatheRequest::createFrom(Request::create('/lathe/api/resources/orders?'.$query));
}

it('reads the page size, clamped to the maximum', function (string $query, int $expected) {
    expect(latheRequestFor($query)->perPage())->toBe($expected);
})->with([
    'default' => ['', 25],
    'allowed' => ['per_page=50', 50],
    'above the maximum' => ['per_page=500', 100],
    'below one' => ['per_page=0', 1],
]);

it('reads the sort direction as asc or desc only', function (string $query, string $expected) {
    expect(latheRequestFor($query)->orderByDirection())->toBe($expected);
})->with([
    ['', 'asc'],
    ['direction=desc', 'desc'],
    ['direction=DESC', 'desc'],
    ['direction=desc;drop', 'asc'],
]);

it('reads trashed as empty, with or only', function (string $query, string $expected) {
    expect(latheRequestFor($query)->trashed())->toBe($expected);
})->with([
    ['', ''],
    ['trashed=with', 'with'],
    ['trashed=only', 'only'],
    ['trashed=all', ''],
]);

it('cuts the search keyword to 100 characters and 8 words', function () {
    $words = latheRequestFor('search='.urlencode('a b c d e f g h i j'))->searchKeyword();
    $chars = latheRequestFor('search='.str_repeat('x', 150))->searchKeyword();

    expect($words)->toBe('a b c d e f g h')
        ->and(mb_strlen($chars))->toBe(100);
});

it('reads the user of the panel guard', function () {
    config([
        'auth.guards.panel' => ['driver' => 'session', 'provider' => 'users'],
        'lathe.guard' => 'panel',
    ]);
    $user = new User;
    auth('panel')->setUser($user);

    expect(latheRequestFor('')->user())->toBe($user);
});
