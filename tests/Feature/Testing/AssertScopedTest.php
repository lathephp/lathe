<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lathe\Testing\InteractsWithLathe;
use Lathe\Tests\Fixtures\Models\Gadget;
use PHPUnit\Framework\AssertionFailedError;

uses(InteractsWithLathe::class);

beforeEach(function () {
    Schema::create('gadgets', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->unsignedInteger('team_id');
        $table->foreignId('invoice_id')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
});

it('passes when every query on the table carries the scope column', function () {
    $this->assertScoped(Gadget::class, fn () => Gadget::all());
});

it('fails for a query that skips the scope', function () {
    try {
        $this->assertScoped(Gadget::class, fn () => Gadget::withoutGlobalScopes()->get());
    } catch (AssertionFailedError $e) {
        expect($e->getMessage())
            ->toContain('team_id')
            ->toContain(quotedSql('from "gadgets"'));

        return;
    }

    $this->fail('assertScoped did not fail');
});

it('ignores queries on other tables', function () {
    $this->assertScoped(Gadget::class, fn () => DB::select('select 1'));
});
