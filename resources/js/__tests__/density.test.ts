/// <reference types="node" />
import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { buttonClass } from '../components/ui/Button';

// Read from disk: Vitest turns a stylesheet import into an empty string.
const nova = readFileSync('resources/css/styles/style-nova.css', 'utf8');

describe('nova density', () => {
  it('holds no 34 px control height', () => {
    expect(nova).not.toContain('34px');
  });

  it('makes the default button 32 px tall', () => {
    expect(nova).toMatch(/\.cn-button-size-default\s*\{\s*@apply h-8 /);
  });
});

// The panel's own buttons and inputs, which first-load pages use in place of the ui package's.
const panel = readFileSync('resources/css/app.css', 'utf8');

describe('panel control density', () => {
  it('holds no 34 px control height', () => {
    expect(panel).not.toContain('34px');
  });

  it("draws the default panel button with nova's 32 px default size", () => {
    expect(buttonClass().split(' ')).toContain('cn-button-size-default');
  });
});

// A phone form's fields share one 44 px height, the select's and the inputs'.
const novaFormRules = [...nova.matchAll(/^\s*(\[data-lathe-form\][^{]*)\{([^}]*)\}/gm)].map((match) => ({ selector: match[1] ?? '', body: match[2] ?? '' }));

describe('a phone form in nova', () => {
  it.each(['input', 'button'])('gives a %s with the input class the 44 px height of the select', (tag) => {
    document.body.innerHTML = `<form data-lathe-form><${tag} class="cn-input"></${tag}></form>`;
    const field = document.querySelector('.cn-input') as HTMLElement;

    expect(novaFormRules.filter((rule) => field.matches(rule.selector.split(',').map((part) => part.trim()).join(', ')) && rule.body.includes('max-md:h-11'))).toHaveLength(1);
  });
});
