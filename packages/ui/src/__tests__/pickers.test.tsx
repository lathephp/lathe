// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { Calendar } from '../calendar';
import { CodeEditor } from '../code-editor';
import {
  Combobox,
  ComboboxChip,
  ComboboxChips,
  ComboboxChipsInput,
  ComboboxContent,
  ComboboxEmpty,
  ComboboxGroup,
  ComboboxInput,
  ComboboxItem,
  ComboboxLabel,
  ComboboxList,
  ComboboxSeparator,
  ComboboxValue,
  useComboboxAnchor,
} from '../combobox';

afterEach(cleanup);

describe('Combobox', () => {
  it('opens with the down arrow, filters by the text typed and picks the item clicked', async () => {
    const change = vi.fn();
    render(
      <Combobox items={['Apple', 'Banana', 'Cherry']} onValueChange={change}>
        <ComboboxInput placeholder="Fruit" showClear />
        <ComboboxContent>
          <ComboboxEmpty>No fruit</ComboboxEmpty>
          <ComboboxList>
            {(item: string) => (
              <ComboboxItem key={item} value={item}>
                {item}
              </ComboboxItem>
            )}
          </ComboboxList>
        </ComboboxContent>
      </Combobox>,
    );
    const input = screen.getByPlaceholderText('Fruit');

    fireEvent.change(input, { target: { value: 'ban' } });
    fireEvent.keyDown(input, { key: 'ArrowDown' });

    await waitFor(() => {
      expect(screen.getAllByRole('option').map((option) => option.textContent)).toEqual(['Banana']);
    });

    fireEvent.click(screen.getByRole('option', { name: 'Banana' }));

    await waitFor(() => {
      expect(change.mock.calls.at(-1)?.[0]).toBe('Banana');
    });
  });

  it('shows the chosen values as chips', () => {
    function Chips() {
      const anchor = useComboboxAnchor();

      return (
        <Combobox items={['Red', 'Blue']} multiple defaultValue={['Red']}>
          <ComboboxChips ref={anchor}>
            <ComboboxValue>
              {(values: string[]) => values.map((value) => <ComboboxChip key={value}>{value}</ComboboxChip>)}
            </ComboboxValue>
            <ComboboxChipsInput aria-label="Colours" />
          </ComboboxChips>
          <ComboboxContent anchor={anchor}>
            <ComboboxList>
              <ComboboxGroup>
                <ComboboxLabel>Colours</ComboboxLabel>
                <ComboboxItem value="Red">Red</ComboboxItem>
                <ComboboxSeparator />
                <ComboboxItem value="Blue">Blue</ComboboxItem>
              </ComboboxGroup>
            </ComboboxList>
          </ComboboxContent>
        </Combobox>
      );
    }
    render(<Chips />);

    expect(screen.getByText('Red').closest('[data-slot="combobox-chip"]')).not.toBeNull();
    expect(screen.getByRole('button', { name: 'Remove' }).getAttribute('data-slot')).toBe('combobox-chip-remove');
  });
});

describe('Calendar', () => {
  it('shows the month and selects the day clicked', () => {
    const select = vi.fn();
    render(<Calendar mode="single" defaultMonth={new Date(2026, 1, 1)} onSelect={select} />);

    expect(screen.getByRole('grid').getAttribute('aria-label')).toBe('February 2026');

    fireEvent.click(screen.getByRole('button', { name: /February 14th, 2026/ }));

    expect((select.mock.calls[0]?.[0] as Date).toDateString()).toBe(new Date(2026, 1, 14).toDateString());
  });
});

describe('CodeEditor', () => {
  it('shows the code with line numbers in the box of a text area', () => {
    const { container } = render(<CodeEditor value={'{"a": 1}\n'} language="json" aria-label="Payload" />);

    expect(container.firstElementChild?.className).toContain('cn-textarea');
    expect(screen.getByRole('textbox', { name: 'Payload' }).textContent).toBe('{"a": 1}');
    expect(container.querySelectorAll('.cm-lineNumbers .cm-gutterElement').length).toBeGreaterThan(1);
  });
});
