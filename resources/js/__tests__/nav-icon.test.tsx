import { render, waitFor } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { NavIcon } from '../components/NavIcon';

describe('NavIcon', () => {
  // First: once the icon set has loaded, later icons draw at once.
  it('holds the icon place while the icon set loads', () => {
    const { container } = render(<NavIcon name="Truck" />);

    expect(container.firstElementChild?.tagName).toBe('SPAN');
    expect(container.firstElementChild?.getAttribute('class')).toContain('size-4');
  });

  it('draws an icon of the Icon enum that is not in the first-load set', async () => {
    const { container } = render(<NavIcon name="Users" />);

    await waitFor(() => {
      expect(container.querySelector('svg.lucide-users')).not.toBeNull();
    });
  });

  it('draws a first-load icon at once and a circle for an unknown name', async () => {
    const { container, rerender } = render(<NavIcon name="ShoppingCart" />);

    expect(container.querySelector('svg.lucide-shopping-cart')).not.toBeNull();

    rerender(<NavIcon name="NoSuchIcon" />);

    await waitFor(() => {
      expect(container.querySelector('svg.lucide-circle')).not.toBeNull();
    });
  });
});
