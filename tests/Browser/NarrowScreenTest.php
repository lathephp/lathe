<?php

use Lathe\SavedTabs\SavedTab;
use Workbench\App\Lathe\Resources\Order;
use Workbench\App\Models\User;

/** How far the page scrolls sideways, in CSS pixels; 0 when it fits the screen. */
const SIDEWAYS_SCROLL = 'document.documentElement.scrollWidth - document.documentElement.clientWidth';

it('keeps the index inside a 375 px screen, the wide table scrolling in its own box', function (string $url) {
    $page = latheVisit('/lathe/login', 'phone-android');
    signInThroughForm($page, 'admin@lathephp.com');

    $page->assertPathIs('/lathe')->resize(375, 812)->navigate($url)
        ->assertSee('Showing 1 to')
        ->assertScript(SIDEWAYS_SCROLL, 0);
})->with([
    'orders' => '/lathe/resources/orders',
    'a lens' => '/lathe/resources/orders/lens/pending-orders',
    'grouped' => '/lathe/resources/orders?group=status:desc',
]);

it('keeps the menu of a saved tab with a long name on a 375 px screen', function () {
    SavedTab::forceCreate([
        'user_id' => (string) User::where('email', 'admin@lathephp.com')->value('id'),
        'resource_class' => Order::class,
        'name' => 'Longname'.str_repeat('x', 92),
        'filters' => ['status' => 'paid'],
        'show_count' => false,
        'position' => 1,
        'schema_hash' => 'x',
    ]);

    $page = latheVisit('/lathe/login', 'phone-android');
    signInThroughForm($page, 'admin@lathephp.com');

    $page->assertPathIs('/lathe')->resize(375, 812)->navigate('/lathe/resources/orders')
        ->assertSee('Showing 1 to')
        ->assertScript("(() => { const button = document.querySelector('button[aria-label^=\"Options for Longname\"]'); return button !== null && button.getBoundingClientRect().right <= document.documentElement.clientWidth; })()", true);
});
