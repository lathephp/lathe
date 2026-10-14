import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { registerBuiltinFields } from '../fields/builtin';
import { DetailField, IndexField } from '../fields/registry';
import type { DetailFieldData, FieldSchema } from '../fields/types';
import { DirectionProvider, useDirection } from '../lib/direction';

registerBuiltinFields();

function schema(component: string): FieldSchema {
  return {
    key: 'f',
    attribute: 'f',
    label: 'F',
    component,
    sortable: false,
    computed: false,
    nullable: false,
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
  };
}

function Probe() {
  return <p>{useDirection()}</p>;
}

describe('direction', () => {
  it('reads rtl from the provider', () => {
    render(
      <DirectionProvider direction="rtl">
        <Probe />
      </DirectionProvider>,
    );

    expect(screen.getByText('rtl')).toBeTruthy();
  });

  it('reads ltr without a provider', () => {
    render(<Probe />);

    expect(screen.getByText('ltr')).toBeTruthy();
  });
});

describe('left to right cells', () => {
  it.each(['email-field', 'number-field', 'currency-field', 'id-field'])('keeps an index %s left to right', (component) => {
    const { container } = render(<IndexField field={schema(component)} cell={{ value: 12, display: '12' }} />);

    expect(container.querySelector('[dir="ltr"]')?.textContent).toContain('12');
  });

  it('keeps a detail code value left to right', () => {
    const field = { ...schema('code-field'), value: 'echo 1;', display: 'echo 1;' } as DetailFieldData;
    const { container } = render(<DetailField field={field} />);

    expect(container.querySelector('[dir="ltr"]')).not.toBeNull();
  });

  it('leaves a name without a direction of its own', () => {
    const { container } = render(<IndexField field={schema('text-field')} cell={{ value: 'Ada', display: 'Ada' }} />);

    expect(container.querySelector('[dir]')).toBeNull();
  });
});
