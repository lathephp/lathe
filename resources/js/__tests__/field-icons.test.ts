import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { afterEach, describe, expect, it, vi } from 'vitest';

describe('field icon names', () => {
  afterEach(() => {
    vi.restoreAllMocks();
    vi.resetModules();
  });

  it('warns once per unknown name and draws a circle', async () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined);
    const { iconNamed } = await import('../fields/display/icons');
    const circle = iconNamed('circle');

    expect(iconNamed('rocket')).toBe(circle);
    expect(warn).toHaveBeenCalledTimes(1);
    expect(String(warn.mock.calls[0]?.[0])).toContain('rocket');

    iconNamed('rocket');
    expect(warn).toHaveBeenCalledTimes(1);

    iconNamed('zeppelin');
    expect(warn).toHaveBeenCalledTimes(2);
  });

  it('never warns for a name the set draws', async () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined);
    const { iconNamed } = await import('../fields/display/icons');

    iconNamed('mail');
    iconNamed('check-circle');

    expect(warn).not.toHaveBeenCalled();
  });

  it('lists every drawn name in the fields docs', () => {
    const source = readFileSync(join(process.cwd(), 'resources/js/fields/display/icons.ts'), 'utf8');
    const names = [...source.matchAll(/^ {2}'?([a-z-]+)'?: [A-Za-z]+,$/gm)].map((match) => match[1]);
    const docs = readFileSync(join(process.cwd(), 'docs/your-first-resource/fields.md'), 'utf8');

    expect(names).toHaveLength(27);
    for (const name of names) {
      expect(docs, name).toContain(`\`${name ?? ''}\``);
    }
  });
});
