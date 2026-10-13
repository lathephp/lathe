import { __panel } from '@lathephp/core';
import { cleanup } from '@testing-library/react';
import { afterEach } from 'vitest';
import { registerBuiltinFields } from '../fields/builtin';

registerBuiltinFields();

// Vitest globals are off, so Testing Library cannot register its own cleanup.
afterEach(() => {
  cleanup();
  __panel.toasts.set([]);
  __panel.sharedProps.set({});
  __panel.setTranslator(null);
});
