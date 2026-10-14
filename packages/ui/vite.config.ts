import { defineConfig } from 'vite';

// The panel serves the real module through its import map. This published copy
// throws on load, so a plugin that bundles it by mistake fails at once.
export default defineConfig({
  build: {
    lib: { entry: { index: 'src/index.ts', chart: 'src/chart.tsx' }, formats: ['es'], fileName: (_format, name) => `${name}.js` },
    emptyOutDir: false,
    rolldownOptions: {
      external: [/^react($|\/)/, /^react-dom($|\/)/, /^@lathephp\//],
      output: {
        banner:
          "throw new Error('@lathephp/ui must run inside the Lathe panel. Mark it external in your build so the panel import map serves it.');",
      },
    },
  },
});
