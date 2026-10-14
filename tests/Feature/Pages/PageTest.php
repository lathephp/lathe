<?php

use Inertia\Testing\AssertableInertia;
use Lathe\Lathe;
use Lathe\Pages\PageRegistry;
use Lathe\Tests\Fixtures\Pages\Closed;
use Workbench\App\Lathe\Pages\UiGallery;
use Workbench\App\Models\User;
use Workbench\Database\Seeders\DemoSeeder;

beforeEach(function () {
    $this->artisan('migrate')->assertSuccessful();
    $this->seed(DemoSeeder::class);
});

function pageUser(string $email = 'admin@lathephp.com'): User
{
    return User::where('email', $email)->firstOrFail();
}

it('renders a page in the shell with its key, base path and sub path', function () {
    $this->actingAs(pageUser());

    $this->get('/lathe/pages/ui-gallery')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Page', false)
        ->where('pageKey', 'ui-gallery')
        ->where('basePath', '/lathe/pages/ui-gallery')
        ->where('path', '')
        ->where('title', 'UI Gallery'));

    $this->get('/lathe/pages/ui-gallery/a/b')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('path', 'a/b'));
});

it('answers 404 for an unknown page and 403 for one the user may not see', function () {
    Lathe::registerPages(Closed::class);
    $this->actingAs(pageUser());

    $this->get('/lathe/pages/nope')->assertNotFound();
    $this->get('/lathe/pages/closed')->assertForbidden();
});

it('registers, finds and lists the pages a user may see', function () {
    $registry = app(PageRegistry::class);
    $registry->register(Closed::class);
    $this->actingAs(pageUser());
    request()->setUserResolver(fn () => pageUser());

    expect($registry->find('ui-gallery'))->toBeInstanceOf(UiGallery::class)
        ->and($registry->find('nope'))->toBeNull()
        ->and(array_map(fn ($p) => $p::uriKey(), $registry->authorized(request())))->toBe(['ui-gallery', 'system-status']);
});

it('gates System Status on the view system status permission', function () {
    $this->actingAs(pageUser())->get('/lathe/pages/system-status')->assertOk();
    $this->actingAs(pageUser('editor@lathephp.com'))->get('/lathe/pages/system-status')->assertForbidden();
});

it('lists System Status under Demo Kit for the admin only', function () {
    $items = function (string $email) {
        $navigation = $this->actingAs(pageUser($email))->get('/lathe')->viewData('page')['props']['navigation'];
        $section = collect($navigation)->firstWhere('label', 'Demo Kit');

        return collect($section['items'] ?? [])->pluck('label')->all();
    };

    expect($items('admin@lathephp.com'))->toBe(['UI Gallery', 'System Status'])
        ->and($items('editor@lathephp.com'))->toBe(['UI Gallery']);
});

it('finds the Demo Kit pages in the palette and checks each page', function () {
    $search = fn (string $email, string $term) => collect($this->actingAs(pageUser($email))
        ->getJson('/lathe/api/search?q='.$term)->assertOk()->json('groups'))->firstWhere('label', 'Demo Kit');

    $gallery = $search('admin@lathephp.com', 'gallery');
    expect(collect($gallery['results'])->pluck('title')->all())->toBe(['UI Gallery'])
        ->and($gallery['results'][0]['url'])->toBe('/pages/ui-gallery');

    expect(collect($search('admin@lathephp.com', 'status')['results'])->pluck('title')->all())->toBe(['System Status'])
        ->and($search('editor@lathephp.com', 'status'))->toBeNull();
});
