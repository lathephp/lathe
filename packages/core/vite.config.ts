import { defineConfig } from 'vite';

// The panel serves the real module through its import map. This published copy
// throws on load, so a plugin that bundles it by mistake fails at once.
// `vite build --mode testing` writes dist/testing.js, the same API with mockLathe()
// and no throw, for a plugin's tests.
export default defineConfig(({ mode }) =>
  mode === 'testing'
    ? {
        build: {
          lib: { entry: 'src/testing/index.ts', formats: ['es'], fileName: 'testing' },
          emptyOutDir: false,
          rolldownOptions: { external: [/^react($|\/)/] },
        },
      }
    : {
        build: {
          lib: { entry: 'src/index.ts', formats: ['es'], fileName: 'index' },
          emptyOutDir: false,
          rolldownOptions: {
            external: ['react'],
            output: {
              banner:
                "throw new Error('@lathephp/core must run inside the Lathe panel. Mark it external in your build so the panel import map serves it.');",
            },
          },
        },
      },
);
