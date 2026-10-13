import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';

// Each page is its own lazy chunk.
const pages = import.meta.glob<{ default: ComponentType }>('./pages/**/*.tsx');

void createInertiaApp({
  resolve: async (name) => {
    const page = pages[`./pages/${name}.tsx`];

    if (page === undefined) {
      throw new Error(`Lathe has no page named "${name}".`);
    }

    return (await page()).default;
  },
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />);
  },
});
