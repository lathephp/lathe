<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Lathe\Lathe;

beforeEach(function () {
    Gate::define('viewLathe', fn () => true);
});

it('turns the panel right to left when enableRTL is called', function () {
    Lathe::enableRTL();

    $html = $this->get('/lathe/login')->assertOk()->getContent();

    expect($html)->toContain('<html lang="en" dir="rtl"')->toContain('"rtl":true');
});

it('stays left to right until enableRTL is called', function () {
    $html = $this->get('/lathe/login')->assertOk()->getContent();

    expect($html)->toContain('dir="ltr"')->toContain('"rtl":false');
});

it('runs the closure once per request and reports the result through isRTL', function () {
    $calls = 0;
    Lathe::enableRTL(function (Request $request) use (&$calls) {
        $calls++;

        return Lathe::resolveUserLocale($request) === 'ar';
    });

    $this->get('/lathe/login')->assertOk();
    $this->get('/lathe/login', ['X-Bench-Locale' => 'ar'])->assertOk()->assertSee('dir="rtl"', false);
    $this->get('/lathe/login')->assertOk()->assertSee('dir="ltr"', false);

    expect($calls)->toBe(3);

    $request = Request::create('/lathe/login');
    expect(Lathe::isRTL($request))->toBeFalse()
        ->and(Lathe::isRTL($request))->toBeFalse()
        ->and($calls)->toBe(4);
});

it('keeps the direction of one request off the next', function () {
    Lathe::enableRTL(fn (Request $request) => $request->header('X-Rtl') === '1');

    $this->get('/lathe/login', ['X-Rtl' => '1'])->assertSee('"rtl":true', false);
    $this->get('/lathe/login')->assertSee('"rtl":false', false);
});
