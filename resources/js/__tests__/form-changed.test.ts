import { describe, expect, it } from 'vitest';
import { formChanged } from '../records/useRecordForm';
import type { FormFieldData } from '../fields/types';

function field(attribute: string, component: string, value: unknown): FormFieldData {
  return { key: attribute, attribute, label: attribute, component, value } as FormFieldData;
}

describe('formChanged', () => {
  it('reads a rating changed and changed back, as a number or as the text its input sends, as unchanged', () => {
    const fields = [field('rating', 'number-field', 4)];

    expect(formChanged({ rating: 2 }, fields)).toBe(true);
    expect(formChanged({ rating: 4 }, fields)).toBe(false);
    expect(formChanged({ rating: '4' }, fields)).toBe(false);
    expect(formChanged({ rating: '' }, fields)).toBe(true);
  });

  it('reads a multi-select emptied back to no choice as unchanged', () => {
    expect(formChanged({ tags: [] }, [field('tags', 'multi-select-field', null)])).toBe(false);
    expect(formChanged({ tags: ['a', 'b'] }, [field('tags', 'multi-select-field', ['a', 'b'])])).toBe(false);
    expect(formChanged({ tags: ['b', 'a'] }, [field('tags', 'multi-select-field', ['a', 'b'])])).toBe(true);
  });

  it('reads a key-value field edited back to the pairs it loaded with as unchanged', () => {
    const fields = [field('details', 'key-value-field', { colour: 'red', size: 4 })];

    expect(formChanged({ details: [{ key: 'colour', value: 'red' }, { key: 'size', value: '4' }] }, fields)).toBe(false);
    expect(formChanged({ details: [{ key: 'colour', value: 'blue' }, { key: 'size', value: '4' }] }, fields)).toBe(true);
    expect(formChanged({ details: [] }, [field('details', 'key-value-field', null)])).toBe(false);
  });

  it('reads a repeater row changed and changed back as unchanged', () => {
    const rows = [{ _key: 3, name: 'Width', value: '10' }];
    const fields = [field('specifications', 'repeater-field', rows)];

    expect(formChanged({ specifications: [{ _key: 3, name: 'Width', value: '12' }] }, fields)).toBe(true);
    expect(formChanged({ specifications: [{ _key: 3, name: 'Width', value: '10' }] }, fields)).toBe(false);
    expect(formChanged({ specifications: [...rows, { name: '', value: '' }] }, fields)).toBe(true);
  });

  it('reads a picked file as a change', () => {
    expect(formChanged({ avatar: new File(['x'], 'a.png') }, [field('avatar', 'image-field', null)])).toBe(true);
  });
});
