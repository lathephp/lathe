import tailwindcss from '@tailwindcss/vite';
import { defineConfig } from 'vite';
// The workbench is no workspace package, so it imports the plugin from its build.
import { lathe } from '../packages/vite-plugin/dist/index.js';

// The root is this folder wherever the build runs, so the output lands in
// workbench/public/vendor/lathe/extensions, which the workbench commits.
export default defineConfig({
  root: import.meta.dirname,
  plugins: [lathe({ mode: 'app' }), tailwindcss()],
});
