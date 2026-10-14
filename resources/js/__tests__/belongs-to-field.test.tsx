import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { BelongsToIndex } from '../fields/relation/BelongsToField';
import type { FieldSchema } from '../fields/types';



const field = { key: 'customer', component: 'belongs-to-field', resource: 'customers' } as unknown as FieldSchema;

describe('BelongsToIndex', () => {
  it('links the related name when the user may view it', () => {
    render(<BelongsToIndex field={field} cell={{ value: { value: 12, display: 'Ann Taylor', href: '/lathe/resources/customers/12' }, display: 'Ann Taylor' }} />);

    expect(screen.getByRole('link', { name: 'Ann Taylor' }).getAttribute('href')).toBe('/lathe/resources/customers/12');
  });

  it('shows plain text when the user may not view it', () => {
    render(<BelongsToIndex field={field} cell={{ value: { value: 12, display: 'Ann Taylor', href: null }, display: 'Ann Taylor' }} />);

    expect(screen.queryByRole('link')).toBeNull();
    expect(screen.getByText('Ann Taylor')).toBeTruthy();
  });

  it('shows a dash with no related record', () => {
    render(<BelongsToIndex field={field} cell={{ value: null, display: '' }} />);

    expect(screen.getByText('—')).toBeTruthy();
  });
});
