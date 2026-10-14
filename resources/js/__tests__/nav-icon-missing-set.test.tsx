import { render, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { NavIcon } from '../components/NavIcon';

// After a deploy the old build's icon set is gone, so its import fails.
vi.mock('../components/icon-set', () => {
  throw new Error('Failed to fetch dynamically imported module');
});

describe('NavIcon without its icon set', () => {
  it('draws a circle instead of failing the page', async () => {
    const { container } = render(<NavIcon name="Users" />);

    await waitFor(() => {
      expect(container.querySelector('svg.lucide-circle')).not.toBeNull();
    });
  });
});
