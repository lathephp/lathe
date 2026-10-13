import { __panel } from '@lathephp/core';
import { cleanup } from '@testing-library/react';
import { afterEach } from 'vitest';

// Vitest globals are off, so Testing Library cannot register its own cleanup.
afterEach(() => {
  cleanup();
  __panel.toasts.set([]);
  __panel.sharedProps.set({});
  __panel.setTranslator((key, replace = {}) =>
    Object.entries(replace).reduce((text, [name, value]) => text.replaceAll(`:${name}`, String(value)), key),
  );
});
