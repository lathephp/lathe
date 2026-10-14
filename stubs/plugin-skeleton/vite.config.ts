import { lathe } from '@lathephp/vite-plugin';
import babel from '@rolldown/plugin-babel';
import react, { reactCompilerPreset } from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

export default defineConfig({
  plugins: [
    lathe({ mode: 'plugin', name: '{{ package }}', entry: 'resources/js/plugin.tsx' }),
    react(),
    babel({ presets: [reactCompilerPreset()] }),
  ],
});
