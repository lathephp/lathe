import { readFileSync } from 'node:fs';
import { defineConfig } from 'vite';

const pkg = JSON.parse(readFileSync(new URL('package.json', import.meta.url), 'utf8')) as {
  exports: Record<string, unknown>;
  dependencies: Record<string, string>;
};

// One entry per export: "." is src/index.ts and "./sidebar" is src/sidebar.tsx.
const entries = Object.fromEntries(
  Object.keys(pkg.exports).map((key) => (key === '.' ? ['index', 'src/index.ts'] : [key.slice(2), `src/${key.slice(2)}.tsx`])),
);

// Every dependency stays external, so the stub that only throws does not carry recharts or Base UI.
const dependencies = Object.keys(pkg.dependencies).map((name) => new RegExp(`^${name.replaceAll('/', '\\/')}($|\\/)`));

// The panel serves the real module through its import map. This published copy
// throws on load, so a plugin that bundles it by mistake fails at once.
export default defineConfig({
  build: {
    lib: { entry: entries, formats: ['es'], fileName: (_format, name) => `${name}.js` },
    emptyOutDir: false,
    rolldownOptions: {
      external: [/^react($|\/)/, /^react-dom($|\/)/, /^@lathephp\//, ...dependencies],
      output: {
        banner:
          "throw new Error('@lathephp/ui must run inside the Lathe panel. Mark it external in your build so the panel import map serves it.');",
      },
    },
  },
});
