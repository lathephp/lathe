import babel from '@rolldown/plugin-babel';
import tailwindcss from '@tailwindcss/vite';
import react, { reactCompilerPreset } from '@vitejs/plugin-react';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import { defineConfig, type Plugin } from 'vite';

// The import map maps each specifier to its own entry file. The manifest lists
// that file under the key "lathe-vendor:{specifier}".
const importMapSpecifiers = [
  'react',
  'react/jsx-runtime',
  'react/compiler-runtime',
  'react-dom',
  'react-dom/client',
  '@lathephp/core',
  '@lathephp/ui',
  '@lathephp/ui/chart',
];

const packageSources: Record<string, string> = {
  '@lathephp/core': 'packages/core/src/index.ts',
  '@lathephp/ui': 'packages/ui/src/index.ts',
  '@lathephp/ui/chart': 'packages/ui/src/chart.tsx',
};

const themeStyles = ['nova', 'vega', 'lyra', 'maia', 'mira', 'luma', 'rhea', 'sera'];

const prefix = 'lathe-vendor:';
const requireFromRoot = createRequire(import.meta.url);

// React's CommonJS files require each other. They stay bundled into the shared
// React chunks, because an external require() would throw in the browser.
const reactInternals = /[\\/]node_modules[\\/]react(-dom)?[\\/]/;

function importMapEntries(): Plugin {
  return {
    name: 'lathe:import-map-entries',
    enforce: 'pre',
    resolveId(id, importer) {
      if (id.startsWith(prefix)) {
        return `\0${id}`;
      }

      if (!importMapSpecifiers.includes(id)) {
        return null;
      }

      if (importer !== undefined && (importer.startsWith(`\0${prefix}`) || reactInternals.test(importer))) {
        return null;
      }

      // A dependency may be CommonJS (use-sync-external-store under Tiptap), and its
      // require() of an external throws in the browser. It imports the vendor entry
      // instead, which is the same module the import map serves.
      if (importer !== undefined && /[\\/]node_modules[\\/]/.test(importer)) {
        return `\0${prefix}${id}`;
      }

      return { id, external: true };
    },
    load(id) {
      if (!id.startsWith(`\0${prefix}`)) {
        return null;
      }

      const specifier = id.slice(prefix.length + 1);
      const source = packageSources[specifier];

      if (source !== undefined) {
        return `export * from ${JSON.stringify(fileURLToPath(new URL(source, import.meta.url)))};\n`;
      }

      // React ships CommonJS, where `export *` yields no named exports, so the
      // entry lists them.
      const names = Object.keys(requireFromRoot(specifier) as object).filter((name) => name !== 'default');

      return `import m from ${JSON.stringify(specifier)};\nexport const { ${names.join(', ')} } = m;\nexport default m;\n`;
    },
  };
}

export default defineConfig({
  // Relative URLs, because PHP serves the build under a path that holds its hash.
  base: './',
  plugins: [importMapEntries(), tailwindcss(), react(), babel({ presets: [reactCompilerPreset()] })],
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    manifest: 'manifest.json',
    rolldownOptions: {
      input: {
        app: 'resources/js/app.tsx',
        // One stylesheet per style, so the panel links only the active one.
        ...Object.fromEntries(themeStyles.map((style) => [`theme-${style}`, `resources/css/theme-${style}.css`])),
        ...Object.fromEntries(
          importMapSpecifiers.map((specifier) => [specifier.replace(/^@/, '').replaceAll('/', '-'), prefix + specifier]),
        ),
      },
      // Keeps the exports of each import map entry.
      preserveEntrySignatures: 'strict',
      // Base 36 keeps "-" and "_" out of the hash, so a name splits on its last "-".
      output: { hashCharacters: 'base36' },
    },
  },
});
