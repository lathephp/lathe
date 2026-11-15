/// <reference types="node" />
import { readFileSync } from 'node:fs';
import { render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { Modal, ModalClose, ModalDescription, ModalFooter, ModalHeader, ModalTitle } from '../components/Modal';
import { Button } from '../components/ui/Button';

// Read from disk: Vitest turns a stylesheet import into an empty string.
const css = readFileSync('resources/css/app.css', 'utf8');

/** The body of the first `@media (<query>)` block that holds `selector`, braces balanced. */
function media(query: string, selector: string): string {
  let from = 0;

  for (;;) {
    const start = css.indexOf(`@media (${query}) {`, from);

    if (start === -1) {
      return '';
    }

    let depth = 0;
    let end = start;

    for (let index = css.indexOf('{', start); index < css.length; index++) {
      depth += css[index] === '{' ? 1 : css[index] === '}' ? -1 : 0;

      if (depth === 0) {
        end = index;
        break;
      }
    }

    const body = css.slice(start, end);

    if (body.includes(selector)) {
      return body;
    }

    from = end;
  }
}

/** The @apply list of a rule, read from `source`. */
function rule(source: string, selector: string): string[] {
  const at = source.indexOf(`${selector} {`);
  const match = /@apply ([^;]+);/.exec(source.slice(at));

  return at === -1 || match === null ? [] : (match[1] ?? '').split(/\s+/);
}

const dialogs = {
  'fields/relation/CreateRelationSheet.tsx': ['ModalTitle', 'ModalFooter'],
  'relations/UpdatePivotModal.tsx': ['ModalHeader', 'ModalTitle', 'ModalFooter'],
  'actions/ImportModal.tsx': ['ModalHeader', 'ModalTitle', 'ModalFooter', 'ModalClose'],
  'actions/DeleteMenu.tsx': ['ModalHeader', 'ModalTitle', 'ModalFooter'],
  'relations/AttachRelationModal.tsx': ['ModalHeader', 'ModalTitle', 'ModalFooter'],
  'records/ConflictDialog.tsx': ['ModalHeader', 'ModalTitle', 'ModalDescription', 'ModalFooter'],
  'actions/ActionModal.tsx': ['ModalHeader', 'ModalTitle', 'ModalFooter', 'ModalClose'],
  'actions/ResponseModal.tsx': ['ModalTitle'],
  'components/KeysSheet.tsx': ['ModalHeader', 'ModalTitle', 'ModalClose'],
  'records/RecordActions.tsx': ['ModalHeader', 'ModalTitle', 'ModalDescription', 'ModalFooter'],
  'fields/repeater/RepeaterFields.tsx': ['ModalHeader', 'ModalTitle', 'ModalFooter'],
  'records/ReauthModal.tsx': ['ModalHeader', 'ModalTitle', 'ModalDescription', 'ModalFooter'],
  'relations/RelationPanel.tsx': ['ModalHeader', 'ModalTitle', 'ModalFooter'],
  'components/ConfirmPasswordDialog.tsx': ['ModalHeader', 'ModalTitle', 'ModalDescription', 'ModalFooter', 'ModalClose'],
  'index/RowMenuPanel.tsx': ['ModalHeader', 'ModalTitle', 'ModalDescription', 'ModalFooter'],
};

describe('the Modal parts', () => {
  it("draw with the style's dialog classes", () => {
    render(
      <Modal labelledBy="title" onClose={vi.fn()}>
        <ModalHeader>
          <ModalTitle id="title">Delete ORD-00001?</ModalTitle>
          <ModalDescription>This cannot be undone.</ModalDescription>
        </ModalHeader>
        <ModalFooter>
          <Button variant="outline">Cancel</Button>
          <Button variant="destructive-solid">Delete</Button>
        </ModalFooter>
        <ModalClose onClick={vi.fn()} />
      </Modal>,
    );

    const dialog = screen.getByRole('dialog', { name: 'Delete ORD-00001?' });
    expect(dialog.classList.contains('cn-dialog-content')).toBe(true);
    expect(within(dialog).getByRole('heading', { name: 'Delete ORD-00001?' }).className).toContain('cn-dialog-title');
    expect(within(dialog).getByText('This cannot be undone.').className).toContain('cn-dialog-description');
    expect(within(dialog).getByText('This cannot be undone.').parentElement?.className).toContain('cn-dialog-header');
    expect(within(dialog).getByRole('button', { name: 'Cancel' }).parentElement?.className).toContain('cn-dialog-footer');
    const close = within(dialog).getByRole('button', { name: 'Close' });
    expect(close.className.split(' ')).toEqual(expect.arrayContaining(['cn-dialog-close', 'cn-button-variant-ghost', 'cn-button-size-icon-sm']));
  });

  it('adds a caller class to the surface', () => {
    render(
      <Modal labelledBy="title" onClose={vi.fn()} className="max-w-[520px]">
        <ModalTitle id="title">Keyboard shortcuts</ModalTitle>
      </Modal>,
    );

    expect(screen.getByRole('dialog', { name: 'Keyboard shortcuts' }).className.split(' ')).toEqual(expect.arrayContaining(['cn-dialog-content', 'max-w-[520px]']));
  });

  it("leave the radius and the shadow to the style: Modal.tsx names neither", () => {
    const source = readFileSync('resources/js/components/Modal.tsx', 'utf8');

    expect(source.match(/\b(rounded|shadow)-[\w[\]-]+/g)).toBeNull();
  });
});

describe('every panel dialog', () => {
  it.each(Object.entries(dialogs))('%s draws its title and footer with the Modal parts', (file, parts) => {
    const source = readFileSync(`resources/js/${file}`, 'utf8');

    expect(parts.filter((part) => !source.includes(`<${part}`))).toEqual([]);
    // A dialog's title is the ModalTitle part, not an h2 of its own.
    expect(source).not.toMatch(/<h2 id=/);
    expect(source).not.toMatch(/modalPanel|modalSurface|modalFooter|\[&_h2\]|h2:has\(\+p\)/);
  });

  it('no longer exports the old class strings', async () => {
    const module: Record<string, unknown> = await import('../components/Modal');

    expect(['modalPanel', 'modalSurface', 'modalFooter'].filter((name) => name in module)).toEqual([]);
  });
});

describe('the Modal on a phone', () => {
  const phone = media('width < 48rem', '[data-lathe-modal]');

  it('is a bottom sheet as wide as the screen, square at the bottom edge, with a grabber', () => {
    // A caller's max-w utility would beat a components rule, so the sheet's width is important.
    expect(rule(phone, '[data-lathe-modal]')).toEqual(expect.arrayContaining(['max-w-none!', 'rounded-b-none', 'border-x-0', 'border-b-0']));
    expect(rule(phone, '[data-lathe-modal]')).not.toContain('rounded-t-2xl');
    expect(rule(phone, '[data-lathe-modal]::before')).toEqual(expect.arrayContaining(['h-1', 'w-9', 'bg-border', "content-['']"]));
  });

  it('stacks the footer buttons 44 px tall with the primary, last in the markup, on top', () => {
    expect(rule(phone, '[data-lathe-modal] .cn-dialog-footer')).toContain('flex-col-reverse');
    expect(rule(phone, '[data-lathe-modal] .cn-dialog-footer > *')).toContain('h-11');
    expect(rule(phone, '[data-lathe-modal] .cn-dialog-footer > *').filter((name) => name.startsWith('rounded'))).toEqual([]);
  });
});
