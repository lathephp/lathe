<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;

class ParityThing extends Model
{
    protected $guarded = [];
}

it('stores both boolean values set at once', function () {
    $field = Field::boolean('Active')->values('yes', 'no');
    $on = new ParityThing;
    $off = new ParityThing;

    $field->fill(LatheRequest::createFrom(Request::create('/', 'POST', ['active' => true])), $on);
    $field->fill(LatheRequest::createFrom(Request::create('/', 'POST', ['active' => false])), $off);

    expect($on->getAttribute('active'))->toBe('yes')->and($off->getAttribute('active'))->toBe('no');
});

it('sends a step for date and date-time inputs', function () {
    expect(Field::date('Due')->step(7)->jsonSerialize()['step'])->toBe(7)
        ->and(Field::dateTime('At')->step(60)->jsonSerialize()['step'])->toBe(60);
});

it('adds badge types over the set ones and takes an icon map through icons()', function () {
    $badge = Field::badge('Level')->types(['vip'])->addTypes(['gold', 'vip'])->map(['v' => 'vip'])->icons(['vip' => 'star']);

    expect($badge->jsonSerialize()['types'])->toBe(['vip', 'gold'])
        ->and($badge->cell(new ParityThing(['level' => 'v']))['icon'] ?? null)->toBe('star');
});
