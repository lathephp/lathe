<?php

namespace Workbench\App\Lathe\Actions;

use Illuminate\Support\Collection;
use Lathe\Actions\Action;
use Lathe\Actions\ActionFields;
use Lathe\Actions\ActionResult;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Workbench\App\Models\Order;

class Refund extends Action
{
    public function __construct()
    {
        parent::__construct();

        $this->withName('Refund')->sole()->icon('banknote');
    }

    public function fields(LatheRequest $request): array
    {
        return [Field::number('Amount')->required()->min(0.01)->step(0.01)];
    }

    public function handle(ActionFields $fields, Collection $models): ActionResult
    {
        $order = $models->whereInstanceOf(Order::class)->firstOrFail();
        $amount = $fields->float('amount');

        if (round($amount * 100) > $order->total_cents) {
            return ActionResult::danger('The refund is more than the order total of $'.number_format($order->total_cents / 100, 2).'.');
        }

        return ActionResult::message('Refunded $'.number_format($amount, 2).'.');
    }
}
