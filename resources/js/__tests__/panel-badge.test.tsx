import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { Badge, toneVariants, type BadgeVariant } from '../components/ui/Badge';

const variants: BadgeVariant[] = ['default', 'secondary', 'outline', 'destructive', 'success', 'warning', 'info'];

describe('the panel Badge', () => {
  it.each(variants)('draws the %s variant with the style classes', (variant) => {
    const { container } = render(<Badge variant={variant}>Paid</Badge>);
    const badge = container.querySelector('[data-slot="badge"]');

    expect(badge?.textContent).toBe('Paid');
    expect(badge?.className).toBe(`lathe-badge cn-badge cn-badge-variant-${variant}`);
  });

  it('takes the default variant when none is given', () => {
    const { container } = render(<Badge>Paid</Badge>);

    expect(container.querySelector('[data-slot="badge"]')?.className).toContain('cn-badge-variant-default');
  });

  it('maps each tone the server sends to a variant', () => {
    expect(toneVariants).toEqual({
      neutral: 'secondary',
      primary: 'default',
      danger: 'destructive',
      destructive: 'destructive',
      info: 'info',
      success: 'success',
      warning: 'warning',
    });
  });
});
