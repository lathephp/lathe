import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Select as SelectComponent } from '../components/ui/Select';

// A fresh module per test, so each starts from the first-paint trigger before the list has loaded.
let Select: typeof SelectComponent;

beforeEach(async () => {
  vi.resetModules();
  ({ Select } = await import('../components/ui/Select'));
});

const statuses = [
  { value: 'pending', label: 'Pending' },
  { value: 'paid', label: 'Paid' },
  { value: 'shipped', label: 'Shipped' },
  { value: 'cancelled', label: 'Cancelled' },
];

describe('Select', () => {
  it('draws a trigger with the chosen label and no browser select', () => {
    const { container } = render(<Select aria-label="Status" value="paid" options={statuses} onChange={vi.fn()} />);

    expect(screen.getByRole('combobox', { name: 'Status' }).textContent).toBe('Paid');
    expect(container.querySelector('select')).toBeNull();
  });

  it.each(['Enter', ' '])('opens the designed list with %j from the first-paint trigger and closes it with Escape', async (key) => {
    render(<Select aria-label="Status" value="pending" options={statuses} onChange={vi.fn()} />);

    const trigger = screen.getByRole('combobox', { name: 'Status' });
    trigger.focus();
    fireEvent.keyDown(trigger, { key });

    const list = await screen.findByRole('listbox');
    expect(screen.getAllByRole('option').map((option) => option.textContent)).toEqual(['Pending', 'Paid', 'Shipped', 'Cancelled']);

    fireEvent.keyDown(list, { key: 'Escape' });
    await waitFor(() => {
      expect(screen.queryByRole('listbox')).toBeNull();
    });
  });

  it('opens on a press and reports the option picked', async () => {
    const onChange = vi.fn();
    render(<Select aria-label="Status" value="pending" options={statuses} onChange={onChange} />);

    fireEvent.mouseDown(screen.getByRole('combobox', { name: 'Status' }), { button: 0 });
    const shipped = await screen.findByRole('option', { name: 'Shipped' });

    // Base UI ignores a click in the first moments after the press that opened the list.
    await waitFor(() => {
      fireEvent.click(shipped);
      expect(onChange).toHaveBeenCalledWith('shipped');
    });
  });
});

describe('Select groups', () => {
  it('shows grouped options under their heading', async () => {
    render(
      <Select
        aria-label="Food"
        value={null}
        options={[
          { value: 'apple', label: 'Apple', group: 'Fruit' },
          { value: 'pear', label: 'Pear', group: 'Fruit' },
          { value: 'kale', label: 'Kale', group: 'Greens' },
        ]}
        onChange={vi.fn()}
      />,
    );

    fireEvent.mouseDown(screen.getByRole('combobox', { name: 'Food' }), { button: 0 });
    await screen.findByRole('listbox');

    expect(screen.getAllByRole('group').map((group) => group.textContent)).toEqual(['FruitApplePear', 'GreensKale']);
  });

  it('calls onFocus when the trigger takes the focus', () => {
    const onFocus = vi.fn();
    render(<Select aria-label="Food" value={null} options={[]} onChange={vi.fn()} onFocus={onFocus} />);

    screen.getByRole('combobox', { name: 'Food' }).focus();

    expect(onFocus).toHaveBeenCalled();
  });
});
