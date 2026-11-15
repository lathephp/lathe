import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { Card, CardAction, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '../components/ui/Card';

// The look comes from the active style's cn-card rules in the style stylesheet.
describe('the panel Card', () => {
  it('draws with the style card class', () => {
    const { container } = render(<Card>Orders</Card>);
    const card = container.firstElementChild as HTMLElement;

    expect(card.dataset.slot).toBe('card');
    expect(card.classList).toContain('cn-card');
  });

  it('puts a caller className after the cn-card class', () => {
    const { container } = render(<Card className="py-0 gap-0">Orders</Card>);
    const classes = [...(container.firstElementChild as HTMLElement).classList];

    expect(classes.indexOf('py-0')).toBeGreaterThan(classes.indexOf('cn-card'));
    expect(classes.indexOf('cn-card')).toBeGreaterThanOrEqual(0);
  });

  it.each([
    ['card-header', 'cn-card-header', CardHeader],
    ['card-title', 'cn-card-title', CardTitle],
    ['card-description', 'cn-card-description', CardDescription],
    ['card-action', 'cn-card-action', CardAction],
    ['card-content', 'cn-card-content', CardContent],
    ['card-footer', 'cn-card-footer', CardFooter],
  ] as const)('draws the %s part', (slot, name, Part) => {
    const { container } = render(<Part className="extra">Part</Part>);
    const part = container.firstElementChild as HTMLElement;
    const classes = [...part.classList];

    expect(part.dataset.slot).toBe(slot);
    expect(classes).toContain(name);
    expect(classes.indexOf('extra')).toBeGreaterThan(classes.indexOf(name));
  });
});
