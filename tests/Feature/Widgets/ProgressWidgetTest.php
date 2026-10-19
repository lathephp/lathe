<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Tests\Fixtures\Widgets\ProgressProbe;
use Lathe\Widgets\Results\ProgressResult;
use Lathe\Widgets\Widget;
use Lathe\Widgets\WidgetPresenter;
use Workbench\App\Models\Order;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    DB::table('customers')->insert(['name' => 'Ada', 'email' => 'ada@example.com', 'country' => 'GB']);
    ProgressProbe::$using = null;
});

/**
 * The `value` field of the widget's response.
 *
 * @return array<string, mixed>
 */
function progressOf(Widget $widget): array
{
    $value = (new WidgetPresenter($widget))->toResponse(LatheRequest::create('/'))['value'];

    return is_array($value) ? $value : [];
}

/**
 * A probe widget that returns this result.
 */
function progressProbe(ProgressResult $result): ProgressProbe
{
    ProgressProbe::$using = fn () => $result;

    return new ProgressProbe;
}

function progressOrder(string $status, int $totalCents): void
{
    static $number = 0;

    DB::table('orders')->insert([
        'customer_id' => 1,
        'number' => sprintf('PRG-%08d', ++$number),
        'status' => $status,
        'total_cents' => $totalCents,
        'placed_at' => '2026-05-01 10:00:00',
        'created_at' => '2026-05-01 10:00:00',
        'updated_at' => '2026-05-01 10:00:00',
    ]);
}

it('computes the percentage of the target', function (int|float $value, int|float $target, float $percentage) {
    expect((new ProgressResult($value, $target))->percentage())->toBe($percentage);
})->with([
    'a third' => [30, 100, 30.0],
    'half' => [45, 90, 50.0],
    'capped at the target' => [200, 100, 100.0],
    'no target' => [5, 0, 0.0],
]);

it('picks the colour from the percentage', function (int|float $percentage, string $color) {
    expect(progressOf(progressProbe(new ProgressResult($percentage, 100)))['color'])->toBe($color);
})->with([
    'just below a third' => [32.99, 'destructive'],
    'a third' => [33, 'warning'],
    'just below two thirds' => [65.99, 'warning'],
    'two thirds' => [66, 'success'],
    'done' => [100, 'success'],
]);

it('reverses the colours for a number to avoid and words the caption for it', function (int $percentage, string $color) {
    $payload = progressOf(progressProbe((new ProgressResult($percentage, 100))->avoid()));

    expect($payload)->toMatchArray(['color' => $color, 'caption' => 'Lower is better', 'avoid' => true]);
})->with([
    'low is good' => [20, 'success'],
    'middle' => [50, 'warning'],
    'high is bad' => [80, 'destructive'],
]);

it('captions a plain progress "of target"', function () {
    expect(progressOf(progressProbe(new ProgressResult(30, 100))))->toMatchArray(['caption' => 'of target', 'avoid' => false]);
});

it('keeps a fixed tone and a label in place of the target colours and caption', function () {
    $result = (new ProgressResult(25, 100))->tone('success')->label('of orders placed in the range');

    expect(progressOf(progressProbe($result)))->toMatchArray(['color' => 'success', 'caption' => 'of orders placed in the range'])
        ->and((new ProgressResult(90, 100))->tone('primary')->color())->toBe('primary');
});

it('refuses a tone that is not a colour of the kit', function () {
    (new ProgressResult(25, 100))->tone('copper');
})->throws(InvalidArgumentException::class);

it('sends the value, the target and the percentage', function () {
    expect(progressOf(progressProbe(new ProgressResult(45, 90))))->toMatchArray(['value' => 45, 'target' => 90, 'percentage' => 50.0])
        ->and(progressOf(progressProbe(new ProgressResult(200, 100))))->toMatchArray(['value' => 200, 'target' => 200, 'percentage' => 100.0]);
});

it('sends prefix, suffix, format and transforms the value on the server', function () {
    $result = (new ProgressResult(250, 500))->dollars()->suffix('order')->withoutSuffixInflection()->format('a')
        ->transform(fn (int $cents) => $cents / 100);

    expect(progressOf(progressProbe($result)))->toMatchArray([
        'prefix' => '$',
        'suffix' => 'order',
        'suffixInflection' => false,
        'format' => 'a',
        'value' => 2.5,
        'percentage' => 50.0,
    ])->and(progressOf(progressProbe((new ProgressResult(1, 2))->euros()))['prefix'])->toBe('€');
});

it('uses the progress-widget component', function () {
    expect((new ProgressProbe)->component())->toBe('progress-widget');
});

it('counts the done rows against all rows', function () {
    progressOrder('paid', 100);
    progressOrder('paid', 100);
    progressOrder('paid', 100);
    progressOrder('open', 100);
    ProgressProbe::$using = fn (LatheRequest $request) => $this->count($request, Order::class, fn (Builder $query) => $query->where('status', 'paid'));

    expect(progressOf(new ProgressProbe))->toMatchArray(['value' => 3, 'target' => 4, 'percentage' => 75.0, 'color' => 'success']);
});

it('counts against a target when one is given', function () {
    progressOrder('paid', 100);
    ProgressProbe::$using = fn (LatheRequest $request) => $this->count($request, Order::class, fn (Builder $query) => $query->where('status', 'paid'), null, 10);

    expect(progressOf(new ProgressProbe))->toMatchArray(['value' => 1, 'target' => 10, 'percentage' => 10.0, 'color' => 'destructive']);
});

it('sums a column of the done rows against the sum of all rows', function () {
    progressOrder('paid', 300);
    progressOrder('open', 100);
    ProgressProbe::$using = fn (LatheRequest $request) => $this->sum($request, Order::class, fn (Builder $query) => $query->where('status', 'paid'), 'total_cents');

    expect(progressOf(new ProgressProbe))->toMatchArray(['value' => 300.0, 'target' => 400.0, 'percentage' => 75.0]);
});
