import { field, type DetailFieldProps, type FieldProps, type IndexFieldProps } from '@lathephp/core';
import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { DetailField, FormField, IndexField } from '../fields/registry';
import type { FieldSchema } from '../fields/types';

function schema(component: string): FieldSchema {
  return {
    key: 'rating',
    attribute: 'rating',
    label: 'Rating',
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

function StarsForm({ onChange }: FieldProps) {
  return (
    <div>
      {[1, 2, 3, 4, 5].map((star) => (
        <button
          key={star}
          type="button"
          onClick={() => {
            onChange(star);
          }}
        >
          {`${String(star)} stars`}
        </button>
      ))}
    </div>
  );
}

const StarsIndex = ({ cell }: IndexFieldProps) => `index ${String(cell.value)}`;
const StarsDetail = ({ field: shown }: DetailFieldProps) => `detail ${String(shown.value)}`;

field('star-rating', { form: StarsForm, index: StarsIndex, detail: StarsDetail });

describe('a field a plugin registers with core', () => {
  it('draws the registered form and passes its value to the form', () => {
    const onChange = vi.fn();
    render(
      <FormField
        field={{ ...schema('star-rating'), value: null }}
        value={null}
        onChange={onChange}
        id="rating"
        describedBy={undefined}
        invalid={false}
        disabled={false}
      />,
    );

    fireEvent.click(screen.getByRole('button', { name: '4 stars' }));

    expect(onChange).toHaveBeenCalledWith(4);
  });

  it('draws the registered index cell and detail value', () => {
    render(<IndexField field={schema('star-rating')} cell={{ value: 4, display: '4' }} />);
    render(<DetailField field={{ ...schema('star-rating'), value: 4, display: '4' }} />);

    expect(screen.getByText('index 4')).toBeTruthy();
    expect(screen.getByText('detail 4')).toBeTruthy();
  });

  it('keeps the component a bare registration gives as the form', () => {
    field('plain-rating', StarsForm);
    render(
      <FormField
        field={{ ...schema('plain-rating'), value: null }}
        value={null}
        onChange={vi.fn()}
        id="plain"
        describedBy={undefined}
        invalid={false}
        disabled={false}
      />,
    );

    expect(screen.getByRole('button', { name: '2 stars' })).toBeTruthy();
  });
});
