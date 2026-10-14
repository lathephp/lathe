import { fireEvent, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import type { LayoutBlock } from '../fields/types';
import { renderPage } from './support';

const resource = { uriKey: 'products', label: 'Products', singularLabel: 'Product', abilities: { create: true } };

function field(overrides: Record<string, unknown>) {
  return {
    key: 'warranty',
    attribute: 'warranty',
    label: 'Warranty',
    component: 'has-one-field',
    sortable: false,
    computed: false,
    nullable: true,
    stacked: false,
    fullWidth: false,
    columnSpan: null,
    indexWidth: null,
    borderless: false,
    compact: false,
    textAlign: 'left',
    placeholder: null,
    help: null,
    helpWidth: null,
    wrapping: false,
    section: null,
    autocomplete: null,
    meta: {},
    kind: 'hasOne',
    relationship: 'warranty',
    resource: 'warranties',
    singularLabel: 'Warranty',
    pluralLabel: 'Warranties',
    value: null,
    display: '',
    ...overrides,
  };
}

function text(attribute: string, label: string) {
  return { ...field({}), key: attribute, attribute, label, component: 'text-field', nullable: false };
}

/** The detail page of product 3 with only its warranty block. */
function renderDetail(warranty: Record<string, unknown>) {
  const block = { type: 'has-one-warranty', key: 'warranty', field: field({}) } as LayoutBlock;

  return renderPage('Resources/Detail', {
    resource,
    record: {
      id: 3,
      title: 'Kettle',
      subtitle: '',
      avatar: null,
      fields: [field({ via: 'products/3/warranty', ...warranty })],
      layout: [block],
      abilities: { view: true, update: false, delete: false, replicate: false, restore: false, forceDelete: false },
      trashed: false,
    },
    breadcrumbs: [],
  });
}

describe('SingularRelationPanel', () => {
  it('shows the related record with a link to its page', () => {
    renderDetail({ record: { id: 7, title: 'Gold plan', href: '/lathe/resources/warranties/7' }, canAdd: false, display: 'Gold plan' });

    const panel = screen.getByRole('region', { name: 'Warranty' });

    expect(within(panel).getByRole('link', { name: 'Gold plan' }).getAttribute('href')).toBe('/lathe/resources/warranties/7');
    expect(within(panel).queryByRole('link', { name: 'Create Warranty' })).toBeNull();
  });

  it('offers Create under the via when empty and allowed', () => {
    renderDetail({ record: null, canAdd: true });

    const panel = screen.getByRole('region', { name: 'Warranty' });

    expect(within(panel).getByText('No Warranty yet')).toBeTruthy();
    expect(within(panel).getByRole('link', { name: 'Create Warranty' }).getAttribute('href')).toBe(
      '/lathe/resources/warranties/new?via=products%2F3%2Fwarranty',
    );
  });

  it('hides Create when the parent is already filled or the user may not add', () => {
    renderDetail({ record: null, canAdd: false });

    const panel = screen.getByRole('region', { name: 'Warranty' });

    expect(within(panel).getByText('No Warranty yet')).toBeTruthy();
    expect(within(panel).queryByRole('link', { name: 'Create Warranty' })).toBeNull();
  });
});

describe('hasOne form', () => {
  it('draws the related fields inline and keeps their values under the field', () => {
    const warranty = { ...field({ fields: [text('type', 'Type'), text('months', 'Months')] }), value: { type: 'silver', months: '6' } };

    renderPage('Resources/Form', {
      resource,
      mode: 'update',
      id: 3,
      title: 'Kettle',
      breadcrumbs: [],
      form: {
        fields: [warranty],
        layout: [
          { type: 'panel', attribute: 'details', name: 'Details', fields: ['warranty'], toolbar: false, collapsible: false, collapsed: false, hideHeading: false, headingInCard: false, help: null, limit: null, columns: null },
        ],
        updatedAt: null,
      },
      submitLabel: 'Update Product',
    });

    const type = screen.getByLabelText<HTMLInputElement>('Type');

    expect(type.value).toBe('silver');
    fireEvent.change(type, { target: { value: 'gold' } });
    expect(screen.getByLabelText<HTMLInputElement>('Type').value).toBe('gold');
    expect(screen.getByLabelText<HTMLInputElement>('Months').value).toBe('6');
  });
});
