<?php

use Lathe\ActionEvents\ActionEvent;
use Workbench\App\Models\Order;

require_once __DIR__.'/records.php';

it('lists the two Finished events of one batch after Mark Shipped on two orders', function () {
    $page = signedInAt('/lathe/resources/orders?filter[status]=paid')->assertPresent('table tbody > tr');
    $labels = $page->script("[...document.querySelectorAll('table tbody > tr input[aria-label^=\"Select \"]')].slice(0, 2).map((box) => box.getAttribute('aria-label'))");
    $orders = Order::whereIn('number', array_map(fn (string $label) => substr($label, 7), $labels))->orderBy('id')->get();

    expect($orders)->toHaveCount(2);

    foreach ($labels as $label) {
        $page->click('[aria-label="'.$label.'"]');
    }

    $page->click('button[aria-haspopup="menu"]:text-is("Actions")')
        ->click('[role="menuitem"]:has-text("Mark Shipped")')
        ->click('Run Action')
        ->assertSee('Action was executed successfully.');

    $events = ActionEvent::orderBy('id')->get();

    expect($events->pluck('actionable_id')->map(fn ($id) => (int) $id)->all())->toBe($orders->pluck('id')->all())
        ->and($events->pluck('batch_id')->unique())->toHaveCount(1);

    $page->navigate('/lathe/resources/action-events')
        ->assertCount('table tbody > tr', 2)
        ->assertScript("[...document.querySelectorAll('table tbody > tr')].map((row) => row.textContent.includes('Mark Shipped') && row.textContent.includes('Finished'))", [true, true]);
    assertCleanPage($page);
});
