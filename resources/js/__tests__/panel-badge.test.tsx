/// <reference types="node" />
import { readFileSync } from 'node:fs';
import { render, screen } from '@testing-library/react';
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

// An inline status cell draws its badge as the trigger: nova's rules find it inside the cell.
const novaSheet = readFileSync('resources/css/styles/style-nova.css', 'utf8');
const inlineRules = [...novaSheet.matchAll(/^\s*(\[data-inline-cell\][^{]*)\{([^}]*)\}/gm)].map((match) => ({ selector: match[1] ?? '', body: match[2] ?? '' }));

describe('a badge in an inline status cell', () => {
  it("takes nova's 24 px inline badge rule", () => {
    render(
      <div data-inline-cell="">
        <Badge variant="success" dot>
          Paid
        </Badge>
      </div>,
    );
    const badge = screen.getByText('Paid');

    expect(inlineRules.filter((rule) => !rule.selector.includes('::') && badge.matches(rule.selector) && rule.body.includes('h-6!'))).toHaveLength(1);
  });
});
