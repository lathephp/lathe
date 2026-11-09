/// <reference types="node" />
import { readdirSync, readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

// The files that wrote their index cells by hand before the snapshot existed. A file may leave
// this list once it reads its cells from indexTable(); none may join it.
const legacy = [
  'actions-menu.test.tsx',
  'attach-relation.test.tsx',
  'board-support.ts',
  'cell-edit.test.tsx',
  'cell-undo.test.tsx',
  'column-picker.test.tsx',
  'delete-menu.test.tsx',
  'fields-date.test.tsx',
  'fields-display.test.tsx',
  'grid-cursor.test.tsx',
  'grid-selection.test.tsx',
  'group-rows.test.tsx',
  'impersonation.test.tsx',
  'index-back.test.tsx',
  'index-pagination.test.tsx',
  'index-rows.test.tsx',
  'index-toolbar-url.test.tsx',
  'index-toolbar.test.tsx',
  'inline-cells.test.tsx',
  'inline-undo.test.tsx',
  'instant-pages.test.tsx',
  'instant-prefetch.test.tsx',
  'lens-selector.test.tsx',
  'panel-events.test.tsx',
  'phone-cards.test.tsx',
  'preview-panel.test.tsx',
  'preview-support.ts',
  'rail-fit.test.tsx',
  'record-action-menu.test.tsx',
  'record-shortcuts.test.tsx',
  'relation-grid.test.tsx',
  'relation-inline-cells.test.tsx',
  'relation-panel.test.tsx',
  'replicate.test.tsx',
  'resource-index.test.tsx',
  'row-links.test.tsx',
  'row-menu.test.tsx',
  'row-selection.test.tsx',
  'run-reload.test.tsx',
  'select-badges.test.tsx',
  'status-meter.test.tsx',
  'summary-row.test.tsx',
  'tag-field.test.tsx',
  'widget-mounts.test.tsx',
];

const folder = 'resources/js/__tests__';

describe('index cell fixtures', () => {
  it('come from the snapshot in every test outside the legacy list', () => {
    const handWritten = readdirSync(folder)
      .filter((file) => /\.tsx?$/.test(file))
      .filter((file) => /\bcells\s*:/.test(readFileSync(`${folder}/${file}`, 'utf8')));
    const added = handWritten.filter((file) => !legacy.includes(file));

    expect(
      added,
      `These files write index cells by hand. Build them from indexTable() in support.tsx, which reads the server's real cells from fixtures/index-payload.json.`,
    ).toEqual([]);
  });
});
