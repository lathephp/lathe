<?php

use Workbench\App\Models\User;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->admin = User::forceCreate(['name' => 'Admin User', 'email' => 'admin@lathephp.com', 'password' => bcrypt('password')]);
});

function sharedNotifications(): array
{
    return test()->actingAs(test()->admin)->get('/lathe')->assertOk()->viewData('page')['props']['notifications'];
}

it('shares the notification settings', function () {
    expect(sharedNotifications())->toBe(['enabled' => true, 'pollingInterval' => 7, 'showUnreadCount' => false]);

    config(['lathe.notifications.enabled' => false, 'lathe.notifications.polling_interval' => 3, 'lathe.notifications.show_unread_count' => true]);

    expect(sharedNotifications())->toBe(['enabled' => false, 'pollingInterval' => 3, 'showUnreadCount' => true]);
});
