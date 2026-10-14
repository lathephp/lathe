<?php

use Illuminate\Contracts\Auth\Access\Gate;
use Pest\Browser\Api\AwaitableWebpage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Workbench\App\Models\User;

require_once __DIR__.'/records.php';

const IMPERSONATION_BANNER = '[data-lathe-impersonation]';

/** The sidebar's section titles, without the chevron a collapsible section adds. */
const SIDEBAR_SECTIONS = "[...document.querySelectorAll('#lathe-sidebar h2')].map((heading) => heading.firstChild.textContent)";

/** The link labels of the sidebar's Demo Kit section, in order. */
const DEMO_KIT_LINKS = "[...[...document.querySelectorAll('#lathe-sidebar h2')].find((heading) => heading.firstChild.textContent === 'Demo Kit').parentElement.querySelectorAll('a')].map((link) => link.textContent)";

/** The status the server answers a GET of System Status with, in the browser's session. */
const SYSTEM_STATUS_CODE = "fetch('/lathe/pages/system-status', { headers: { Accept: 'text/html' } }).then((response) => response.status)";

/** The texts of the innermost elements in a detail row's value, such as the badges of a multi-select. */
function detailValues(AwaitableWebpage $page, string $label): mixed
{
    return $page->script("[...[...document.querySelectorAll('main div')].find((row) => row.children.length === 2 && row.firstElementChild.textContent === '{$label}')"
        ."?.lastElementChild.querySelectorAll('*') ?? []].filter((node) => node.children.length === 0).map((node) => node.textContent)");
}

/** Start impersonating Editor User from the Users index row menu. */
function impersonateEditor(AwaitableWebpage $page): AwaitableWebpage
{
    return $page->navigate('/lathe/resources/users')
        ->click('[aria-label="Actions for Editor User"]')
        ->click('[role="menuitem"]:text-is("Impersonate")')
        ->assertPathIs('/lathe')
        ->assertVisible(IMPERSONATION_BANNER);
}

function stopImpersonating(AwaitableWebpage $page): AwaitableWebpage
{
    return $page->click(IMPERSONATION_BANNER.' button:text-is("Stop Impersonating")')
        ->assertPathIs('/lathe/resources/users')
        ->assertNotPresent(IMPERSONATION_BANNER);
}

it('grants System Status to Editor User through an editor role made in the panel', function () {
    $page = signedInAt('/lathe')->assertPresent('#lathe-sidebar h2');

    // Create the editor role with two permissions the picker searches on the server.
    expect($page->script(SIDEBAR_SECTIONS))->toContain('Access Control');

    $page->click('#lathe-sidebar a[href$="/resources/roles"]')
        ->assertPathIs('/lathe/resources/roles')
        ->click('a[aria-keyshortcuts="c"][href$="/resources/roles/new"]')
        ->assertPathIs('/lathe/resources/roles/new')
        ->type('#field-name', 'editor')
        ->click('#field-permissions')
        ->assertPresent('#field-permissions option[value="edit orders"]')
        ->select('#field-permissions', ['view orders', 'edit orders'])
        ->click('form button[type="submit"]')
        ->assertScript('/^\/lathe\/resources\/roles\/\d+$/.test(location.pathname)')
        ->assertScript("document.querySelector('main h1')?.textContent", 'editor');

    $role = Role::findByName('editor');

    expect(detailValues($page, 'Permissions'))->toBe(['edit orders', 'view orders'])
        ->and($role->permissions->pluck('name')->sort()->values()->all())->toBe(['edit orders', 'view orders']);

    // Open it from Roles and add view system status.
    $page->navigate('/lathe/resources/roles')
        ->click('table tbody tr:has-text("editor") td:nth-child(3)')
        ->assertPathIs("/lathe/resources/roles/{$role->id}")
        ->click("a[href$=\"/resources/roles/{$role->id}/edit\"]")
        ->assertPathIs("/lathe/resources/roles/{$role->id}/edit")
        ->click('#field-permissions')
        ->assertPresent('#field-permissions option[value="view system status"]')
        ->select('#field-permissions', ['view orders', 'edit orders', 'view system status'])
        ->click('form button[type="submit"]')
        ->assertPathIs("/lathe/resources/roles/{$role->id}");

    expect(detailValues($page, 'Permissions'))->toBe(['edit orders', 'view orders', 'view system status'])
        ->and($role->fresh()?->permissions->pluck('name')->sort()->values()->all())->toBe(['edit orders', 'view orders', 'view system status']);

    // Before the grant the editor sees UI Gallery alone and System Status answers 403.
    impersonateEditor($page);
    expect($page->script(DEMO_KIT_LINKS))->toBe(['UI Gallery'])
        ->and($page->script(SYSTEM_STATUS_CODE))->toBe(403);
    stopImpersonating($page);

    // Give Editor User the role.
    $editor = User::where('email', 'editor@lathephp.com')->sole();
    $page->click('[aria-label="Actions for Editor User"]')
        ->click('[role="menuitem"]:text-is("Edit")')
        ->assertPathIs("/lathe/resources/users/{$editor->id}/edit")
        ->select('#field-roles', ['editor'])
        ->click('form button[type="submit"]')
        ->assertPathIs("/lathe/resources/users/{$editor->id}");

    expect(detailValues($page, 'Roles'))->toBe(['editor'])
        ->and($editor->fresh()?->getRoleNames()->all())->toBe(['editor']);

    // After it the editor sees System Status beside UI Gallery, and System Status answers 200.
    impersonateEditor($page)
        ->assertSeeIn(IMPERSONATION_BANNER, 'You are currently impersonating Editor User.')
        ->click('[data-lathe-sidebar] button[aria-haspopup="menu"]')
        ->assertPresent('#lathe-user-menu');

    expect($page->script("[...document.querySelectorAll('#lathe-user-menu [role=\"none\"] p')].map((line) => line.textContent)"))->toBe(['Editor User', 'editor@lathephp.com'])
        ->and($page->script(DEMO_KIT_LINKS))->toBe(['UI Gallery', 'System Status'])
        ->and($page->script(SYSTEM_STATUS_CODE))->toBe(200);

    stopImpersonating($page);
    assertCleanPage($page);
});

it('hides Access Control and answers Roles with the 403 page without the default policies', function () {
    // What a provider without withDefaultPolicies() leaves: the plugin maps no policy to Spatie's models.
    $registrar = app(PermissionRegistrar::class);
    $gate = app(Gate::class);
    $policies = (fn () => $this->policies)->call($gate);
    unset($policies[$registrar->getRoleClass()], $policies[$registrar->getPermissionClass()]);
    (fn () => $this->policies = $policies)->call($gate);

    $page = signedInAt('/lathe')->assertPresent('#lathe-sidebar h2');

    expect($page->script(SIDEBAR_SECTIONS))->toContain('Demo Kit')->not->toContain('Access Control');

    $page->assertNotPresent('#lathe-sidebar a[href$="/resources/roles"]')
        ->navigate('/lathe/resources/roles')
        ->assertSee('Forbidden')
        ->assertSee('RoleResource has no policy for Spatie\Permission\Models\Role, so every ability is denied.');
    assertCleanPage($page);
});
