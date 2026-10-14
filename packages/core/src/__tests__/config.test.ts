import { describe, expect, it } from 'vitest';
import { __panel, config } from '../index';

describe('config()', () => {
  it('reads a value the server provided with provideToScript()', () => {
    __panel.sharedProps.set({ provided: { insightsViewer: 'Ada' } });

    expect(config('insightsViewer')).toBe('Ada');
    expect(config('missing')).toBeUndefined();
  });
});
