<?php

use Lathe\Enums\Icon;
use Lathe\Lathe;
use Lathe\Notifications\LatheNotification;
use Lathe\Notifications\NotificationAction;
use Lathe\Notifications\NotificationChannelRegistry;
use Workbench\App\Models\User;

test('to_array_holds_icon_message_and_type_by_default', function () {
    $notification = (new LatheNotification)->message('Saved');

    expect($notification->toArray())->toBe(['icon' => 'info', 'message' => 'Saved', 'type' => 'info']);
});

test('each_type_helper_sets_its_type_and_icon', function (string $method, string $icon) {
    $array = (new LatheNotification)->message('x')->{$method}()->toArray();

    expect($array['type'])->toBe($method)->and($array['icon'])->toBe($icon);
})->with([
    ['success', 'check-circle'],
    ['error', 'circle-x'],
    ['warning', 'alert-triangle'],
    ['info', 'info'],
]);

test('to_array_holds_body_actions_and_duration_when_set', function () {
    $notification = (new LatheNotification)
        ->message('Export ready')
        ->body('1,200 rows')
        ->icon(Icon::Download)
        ->action('Open', '/lathe/orders')
        ->actionButton('Download', 'https://example.com/file.csv', Icon::Download)
        ->actionIcon('Tip', '/tip', Icon::Info)
        ->openInNewTab()
        ->duration(8000);

    expect($notification->toArray())->toBe([
        'icon' => 'download',
        'message' => 'Export ready',
        'type' => 'info',
        'body' => '1,200 rows',
        'actions' => [
            ['text' => 'Open', 'url' => '/lathe/orders', 'icon' => null, 'style' => 'link', 'openInNewTab' => false],
            ['text' => 'Download', 'url' => 'https://example.com/file.csv', 'icon' => 'download', 'style' => 'button', 'openInNewTab' => false],
            ['text' => 'Tip', 'url' => '/tip', 'icon' => 'info', 'style' => 'icon', 'openInNewTab' => true],
        ],
        'duration' => 8000,
    ])->and($notification->toDatabase(new User))->toBe($notification->toArray());
});

test('open_in_new_tab_without_an_action_has_no_effect', function () {
    $array = (new LatheNotification)->message('x')->openInNewTab()->toArray();

    expect($array)->not->toHaveKey('actions');
});

test('actions_accepts_prebuilt_actions', function () {
    $array = (new LatheNotification)->message('x')->actions(NotificationAction::button('Go', '/go'), NotificationAction::iconButton('Go', '/go', Icon::Eye)->openInNewTab())->toArray();

    expect(array_column($array['actions'], 'style'))->toBe(['button', 'icon'])
        ->and(array_column($array['actions'], 'openInNewTab'))->toBe([false, true]);
});

test('a_non_http_action_url_throws', function (string $url) {
    expect(fn () => (new LatheNotification)->action('Go', $url))->toThrow(InvalidArgumentException::class);
    expect(fn () => NotificationAction::make('Go', $url))->toThrow(InvalidArgumentException::class);
})->with(['javascript:alert(1)', 'data:text/html,x', 'ftp://example.com/x']);

test('via_returns_database_plus_registered_channels_without_duplicates', function () {
    $registry = new NotificationChannelRegistry(fn () => false);
    $registry->add('slack', 'database', 'slack');
    app()->instance(NotificationChannelRegistry::class, $registry);

    expect((new LatheNotification)->via(new User))->toBe(['database', 'slack']);
});

test('registering_a_channel_after_boot_throws', function () {
    expect(fn () => Lathe::notificationChannels('slack'))->toThrow(LogicException::class);
});
