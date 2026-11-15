/// <reference types="node" />
import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { buttonClass } from '../components/ui/Button';

// Read from disk: Vitest turns a stylesheet import into an empty string.
const css = readFileSync('resources/css/app.css', 'utf8');
// A selector list splits at its top-level commas only, not those inside :where() or :not().
const toolbarSelectors = [...css.matchAll(/^\s*(\.lathe-toolbar[^{]*?)\s*\{/gm)].flatMap((match) => (match[1] ?? '').split(/,(?![^(]*\))/));

function button(className = buttonClass('ghost', 'sm')): HTMLButtonElement {
  const element = document.createElement('button');
  element.className = className;
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
    const menu = button('cn-button cn-button-variant-default cn-button-size-default');
    document.querySelector('.lathe-toolbar > div')?.append(own, menu);

    expect(toolbarSelectors.filter((selector) => own.matches(selector))).toEqual([expect.stringContaining('.cn-button-size-sm')]);
    expect(toolbarSelectors.filter((selector) => menu.matches(selector))).toEqual([expect.stringContaining(':not(.lathe-button)')]);
  });

  it("leave the panel button's own look to the style", () => {
    document.body.innerHTML = '<div class="lathe-toolbar"><div></div></div>';
    const own = button(buttonClass('outline', 'default'));
    document.querySelector('.lathe-toolbar > div')?.append(own);

    expect(toolbarSelectors.filter((selector) => own.matches(selector))).toEqual([]);
  });
});
