<?php

use Illuminate\Support\Facades\DB;
use Lathe\Testing\InteractsWithLathe;
use PHPUnit\Framework\AssertionFailedError;

uses(InteractsWithLathe::class);

it('passes when the closure runs exactly that many queries', function () {
    $this->assertQueryCount(2, function () {
        DB::select('select 1');
        DB::select('select 2');
    });
});

it('fails with the SQL listed when the closure runs 3 queries', function () {
    try {
        $this->assertQueryCount(2, function () {
            DB::select('select 1 as one');
            DB::select('select 2 as two');
            DB::select('select 3 as three');
        });
    } catch (AssertionFailedError $e) {
        expect($e->getMessage())
            ->toContain('Expected 2 queries, ran 3')
            ->toContain('1. select 1 as one')
            ->toContain('3. select 3 as three');

        return;
    }

    $this->fail('assertQueryCount did not fail');
});
