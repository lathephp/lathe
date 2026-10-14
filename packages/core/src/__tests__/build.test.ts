/// <reference types="node" />
import { mkdtempSync, readdirSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { build } from 'vite';
import { afterAll, describe, expect, it } from 'vitest';

const packages = fileURLToPath(new URL('../../..', import.meta.url));
const outDir = mkdtempSync(join(tmpdir(), 'lathe-packages-'));

async function built(folder: string, mode = 'production'): Promise<string> {
  const root = join(packages, folder);
  const out = join(outDir, folder, mode);
  await build({ root, mode, configFile: join(root, 'vite.config.ts'), logLevel: 'silent', build: { outDir: out } });

  return out;
}

afterAll(() => {
  rmSync(outDir, { recursive: true, force: true });
});

describe('the published builds', () => {
  it('pins the throw of @lathephp/core on load outside the panel', async () => {
    const index = readFileSync(join(await built('core'), 'index.js'), 'utf8');

    expect(index).toMatch(/^throw (new )?Error\(["']@lathephp\/core must run inside the Lathe panel\./);
  });

  it('ship @lathephp/core/testing without the throw', async () => {
    const out = await built('core', 'testing');
    const files = readdirSync(out).filter((file) => file.endsWith('.js'));

    expect(readFileSync(join(out, 'testing.js'), 'utf8')).toMatch(/\bmockLathe\b/);
    expect(files.filter((file) => readFileSync(join(out, file), 'utf8').includes('must run inside the Lathe panel'))).toEqual([]);
  });

  it('pins the throw of @lathephp/ui on load outside the panel', async () => {
    const index = readFileSync(join(await built('ui'), 'index.js'), 'utf8');

    expect(index).toMatch(/^throw (new )?Error\(["']@lathephp\/ui must run inside the Lathe panel\./);
  });
}, 60_000);
