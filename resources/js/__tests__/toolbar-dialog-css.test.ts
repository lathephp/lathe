/// <reference types="node" />
import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

// Read from disk: Vitest turns a stylesheet import into an empty string.
const css = readFileSync('resources/css/app.css', 'utf8');
// A selector list splits at its top-level commas only, not those inside :where() or :not().
const toolbarSelectors = [...css.matchAll(/^\s*(\.lathe-toolbar[^{]*?)\s*\{/gm)].flatMap((match) => match[1].split(/,(?![^(]*\))/));

function button(): HTMLButtonElement {
  const element = document.createElement('button');
  element.className = 'cn-button cn-button-variant-default lathe-button lathe-button-size-sm';
  element.append(document.createElementNS('http://www.w3.org/2000/svg', 'svg'));

  return element;
}

// An action's dialog renders inside the toolbar that opened it, while no row is selected.
describe('toolbar rules and the dialogs it opens', () => {
  it('reads the toolbar rules', () => {
    expect(toolbarSelectors.length).toBeGreaterThan(3);
  });

  it.each(['dialog', 'alertdialog'])('leave a button inside a %s alone', (role) => {
    document.body.innerHTML = '<div class="lathe-toolbar"><div><div></div></div></div>';
    const dialog = document.createElement('div');
    dialog.setAttribute('role', role);
    const inner = button();
    dialog.append(inner);
    document.querySelector('.lathe-toolbar > div > div')?.append(dialog);

    const reaching = toolbarSelectors.filter((selector) => inner.matches(selector) || inner.querySelector('svg')?.matches(selector));

    expect(reaching).toEqual([]);
  });

  it("still dress the toolbar's own buttons", () => {
    document.body.innerHTML = '<div class="lathe-toolbar"><div></div></div>';
    const own = button();
    document.querySelector('.lathe-toolbar > div')?.append(own);

    expect(toolbarSelectors.filter((selector) => own.matches(selector)).length).toBeGreaterThanOrEqual(2);
  });
});
