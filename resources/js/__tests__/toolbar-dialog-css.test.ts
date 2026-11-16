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

  it("leave the kit's small buttons alone while rows are ticked", () => {
    document.body.innerHTML = '<div class="lathe-toolbar" data-selecting=""><div></div></div>';
    const kit = button('cn-button cn-button-variant-outline cn-button-size-sm');
    document.querySelector('.lathe-toolbar > div')?.append(kit);

    expect(toolbarSelectors.filter((selector) => kit.matches(selector))).toEqual([]);
  });

  it("leave the panel button's own look to the style", () => {
    document.body.innerHTML = '<div class="lathe-toolbar"><div></div></div>';
    const own = button(buttonClass('outline', 'default'));
    document.querySelector('.lathe-toolbar > div')?.append(own);

    expect(toolbarSelectors.filter((selector) => own.matches(selector))).toEqual([]);
  });
});

// The sidebar toggle's aria-expanded tells the sidebar's state, not an open menu.
const topbarRules = [...css.matchAll(/^\s*(\.lathe-topbar[^{]*?)\s*\{([^}]*)\}/gm)].map((match) => ({ selector: match[1] ?? '', body: match[2] ?? '' }));

describe('the top bar', () => {
  it("keeps an open sidebar's toggle on the ghost ground", () => {
    document.body.innerHTML = '<header class="lathe-topbar"></header>';
    const toggle = button(buttonClass('ghost', 'icon'));
    toggle.setAttribute('aria-controls', 'lathe-sidebar');
    toggle.setAttribute('aria-expanded', 'true');
    document.querySelector('.lathe-topbar')?.append(toggle);

    expect(topbarRules.filter((rule) => toggle.matches(rule.selector) && rule.body.includes('bg-transparent'))).toHaveLength(1);
  });

  it("leaves the phone's primary page action its own colours", () => {
    document.body.innerHTML = '<header class="lathe-topbar"></header>';
    const create = document.createElement('a');
    create.className = `${buttonClass('default', 'icon')} lathe-page-bar-action`;
    document.querySelector('.lathe-topbar')?.append(create);

    expect(topbarRules.filter((rule) => create.matches(rule.selector))).toEqual([]);
  });
});

// The save bar on a phone: 44 px buttons, Cancel on a card ground, the second save hidden and the main one filling the row.
const footerRules = [...css.matchAll(/^\s*(\.lathe-form-footer \.[^{]*?)\s*\{([^}]*)\}/gm)].map((match) => ({ selector: match[1] ?? '', body: match[2] ?? '' }));

describe('the form footer hooks', () => {
  it.each([
    ['ghost', 'max-md:bg-card'],
    ['outline', 'max-md:hidden'],
    ['default', 'max-md:flex-1'],
  ] as const)('reach the %s button', (variant, utility) => {
    document.body.innerHTML = '<div class="lathe-form-footer"><div></div></div>';
    const own = button(buttonClass(variant));
    document.querySelector('.lathe-form-footer > div')?.append(own);
    const reaching = footerRules.filter((rule) => own.matches(rule.selector));

    expect(reaching.some((rule) => rule.body.includes('max-md:h-11'))).toBe(true);
    expect(reaching.some((rule) => rule.body.includes(utility))).toBe(true);
  });
});

describe('the top bar hooks', () => {
  it('reach its ghost icon buttons, 44 px on a phone', () => {
    document.body.innerHTML = '<header class="lathe-topbar"></header>';
    const bell = button(buttonClass('ghost', 'icon'));
    document.querySelector('.lathe-topbar')?.append(bell);

    expect(topbarRules.some((rule) => bell.matches(rule.selector) && rule.body.includes('size-11'))).toBe(true);
  });
});

describe('the Group control', () => {
  // A utility on .lathe-group beats every style's own select focus rule, and sera marks the focus
  // only with the trigger's bottom border, so a border-0 here left it no focus at all.
  it('keeps the style border width, clear at rest and in the ring colour on focus', () => {
    const rule = /\.lathe-group \{[^}]*\}/.exec(css)?.[0] ?? '';

    expect(rule).not.toMatch(/\bborder-0\b/);
    expect(rule).toMatch(/\bborder-transparent\b/);
    expect(rule).toMatch(/\bfocus-visible:border-ring\b/);
  });
});
