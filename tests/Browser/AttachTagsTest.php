<?php

use Pest\Browser\Api\AwaitableWebpage;
use Workbench\App\Models\Product;
use Workbench\App\Models\Tag;

require_once __DIR__.'/records.php';

const TAGS_PANEL = 'section[aria-label="Tags"]';
const ATTACH_DIALOG = '[role="dialog"]';
// The position cell of each listed tag, keyed by the tag's name, read through the panel's column headers.
const TAG_POSITIONS = '(() => { const panel = document.querySelector(\'section[aria-label="Tags"]\'); const heads = [...panel.querySelectorAll("thead th")].map((th) => th.textContent.trim()); const name = heads.indexOf("Name"); const position = heads.indexOf("Position"); return Object.fromEntries([...panel.querySelectorAll("tbody tr")].map((row) => [row.cells[name].textContent.trim(), row.cells[position].textContent.trim()])); })()';

/** Open the attach modal's picker, search for a tag by name and pick it. */
function pickTag(AwaitableWebpage $page, string $name): AwaitableWebpage
{
    return $page->click(ATTACH_DIALOG.' button[aria-haspopup="listbox"]')
        ->type(ATTACH_DIALOG.' input[type="search"]', $name)
        // The picker waits its 500 ms debounce before it asks again.
        ->wait(1.5)
        ->click(ATTACH_DIALOG.' [role="option"]:text-is("'.$name.'")')
        ->assertSeeIn(ATTACH_DIALOG.' button[aria-haspopup="listbox"]', $name);
}

it('attaches two tags with positions, edits one and detaches it', function () {
    $attached = Product::findOrFail(1)->tags()->pluck('tags.id');
    $second = Tag::query()->where('name', '!=', 'sale')->whereNotIn('id', $attached)->orderBy('id')->firstOrFail()->name;

    $page = signedInAt('/lathe/resources/products/1');

    $page->click(TAGS_PANEL.' button:text-is("Attach")');
    pickTag($page, 'sale')
        ->type(ATTACH_DIALOG.' input[name="position"]', '1')
        ->click(ATTACH_DIALOG.' button:text-is("Attach & attach another")')
        ->assertSeeIn(ATTACH_DIALOG.' button[aria-haspopup="listbox"]', 'Select Tag');
    pickTag($page, $second)
        ->type(ATTACH_DIALOG.' input[name="position"]', '2')
        ->click(ATTACH_DIALOG.' button:text-is("Attach")')
        ->assertNotPresent(ATTACH_DIALOG)
        ->assertScript(TAG_POSITIONS.'["sale"]', '1')
        ->assertScript(TAG_POSITIONS.'['.json_encode($second).']', '2');

    $page->click(TAGS_PANEL.' tbody tr:has-text("sale") button:text-is("Edit pivot")')
        ->assertValue(ATTACH_DIALOG.' input[name="position"]', '1')
        ->type(ATTACH_DIALOG.' input[name="position"]', '3')
        ->click(ATTACH_DIALOG.' button:text-is("Update")')
        ->assertNotPresent(ATTACH_DIALOG)
        ->assertScript(TAG_POSITIONS.'["sale"]', '3');

    $page->click(TAGS_PANEL.' tbody tr:has-text("sale") button:text-is("Detach")')
        ->click(ATTACH_DIALOG.' button:text-is("Detach")')
        ->assertScript('"sale" in '.TAG_POSITIONS, false)
        ->assertScript(TAG_POSITIONS.'['.json_encode($second).']', '2');

    $tags = Product::findOrFail(1)->tags()->get();
    expect($tags->firstWhere('name', 'sale'))->toBeNull()
        ->and((int) $tags->firstWhere('name', $second)?->pivot->position)->toBe(2);
    assertCleanPage($page);
});

it('shows no Attach button to an editor', function () {
    $page = signedInAt('/lathe/resources/products/1', email: 'editor@lathephp.com');

    $page->assertPresent(TAGS_PANEL.' tbody tr')
        ->assertNotPresent(TAGS_PANEL.' button:text-is("Attach")');
    assertCleanPage($page);
});
