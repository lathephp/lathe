// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import {
  Field,
  FieldContent,
  FieldDescription,
  FieldError,
  FieldGroup,
  FieldLabel,
  FieldLegend,
  FieldSeparator,
  FieldSet,
  FieldTitle,
} from '../field';
import {
  Checkbox,
  Input,
  Label,
  RadioGroup,
  RadioGroupItem,
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectSeparator,
  SelectTrigger,
  SelectValue,
  Slider,
  Switch,
  Textarea,
} from '../index';
import {
  InputGroup,
  InputGroupAddon,
  InputGroupButton,
  InputGroupInput,
  InputGroupText,
  InputGroupTextarea,
} from '../input-group';
import { OTPField, OTPFieldGroup, OTPFieldSeparator, OTPFieldSlot } from '../otp-field';

afterEach(cleanup);

describe('Input, Textarea and Label', () => {
  it('render text controls named by their label', () => {
    const change = vi.fn();
    render(
      <>
        <Label htmlFor="name">Name</Label>
        <Input
          id="name"
          onChange={(event) => {
            change(event.target.value);
          }}
        />
        <Label htmlFor="notes">Notes</Label>
        <Textarea id="notes" defaultValue="Fragile" />
      </>,
    );

    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Ada' } });

    expect(change).toHaveBeenCalledWith('Ada');
    expect(screen.getByLabelText<HTMLTextAreaElement>('Notes').value).toBe('Fragile');
  });
});

describe('Checkbox, Switch and RadioGroup', () => {
  it('toggle their checked state on click', () => {
    render(
      <>
        <Checkbox aria-label="Paid" />
        <Switch aria-label="Notify" size="sm" />
      </>,
    );
    const paid = screen.getByRole('checkbox', { name: 'Paid' });
    const notify = screen.getByRole('switch', { name: 'Notify' });

    fireEvent.click(paid);
    fireEvent.click(notify);

    expect(paid.getAttribute('aria-checked')).toBe('true');
    expect(notify.getAttribute('aria-checked')).toBe('true');
    expect(notify.getAttribute('data-size')).toBe('sm');
  });

  it('moves the radio choice with the arrow keys', async () => {
    render(
      <RadioGroup aria-label="Size" defaultValue="s">
        <RadioGroupItem value="s" aria-label="Small" />
        <RadioGroupItem value="l" aria-label="Large" />
      </RadioGroup>,
    );
    const small = screen.getByRole('radio', { name: 'Small' });
    small.focus();

    fireEvent.keyDown(small, { key: 'ArrowDown' });

    await waitFor(() => {
      expect(screen.getByRole('radio', { name: 'Large' }).getAttribute('aria-checked')).toBe('true');
    });
  });
});

describe('Slider', () => {
  it('raises its value with the right arrow', async () => {
    render(<Slider aria-label="Volume" defaultValue={[20]} />);
    // Base UI hides the thumb until it measures the track, which jsdom never lays out.
    const thumb = screen.getByRole('slider', { hidden: true });
    thumb.focus();

    fireEvent.keyDown(thumb, { key: 'ArrowRight' });

    await waitFor(() => {
      expect(thumb.getAttribute('aria-valuenow')).toBe('21');
    });
  });
});

describe('Select', () => {
  it('opens its list and picks an item with Enter', async () => {
    const change = vi.fn();
    render(
      <Select onValueChange={change}>
        <SelectTrigger size="sm" aria-label="Status">
          <SelectValue placeholder="Pick one" />
        </SelectTrigger>
        <SelectContent>
          <SelectGroup>
            <SelectLabel>Statuses</SelectLabel>
            <SelectItem value="paid">Paid</SelectItem>
            <SelectSeparator />
            <SelectItem value="refunded">Refunded</SelectItem>
          </SelectGroup>
        </SelectContent>
      </Select>,
    );

    fireEvent.click(screen.getByRole('combobox', { name: 'Status' }));
    const refunded = await screen.findByRole('option', { name: 'Refunded' });
    fireEvent.keyDown(refunded, { key: 'Enter' });

    expect(change.mock.calls[0]?.[0]).toBe('refunded');
    expect(screen.getByRole('combobox', { name: 'Status' }).getAttribute('data-size')).toBe('sm');
  });
});

describe('Field and InputGroup', () => {
  it('lay out a labelled field with its description, error and addons', () => {
    render(
      <FieldSet>
        <FieldLegend>Contact</FieldLegend>
        <FieldGroup>
          <Field orientation="horizontal">
            <FieldContent>
              <FieldLabel htmlFor="email">Email</FieldLabel>
              <FieldTitle>Work email</FieldTitle>
              <FieldDescription>We never share it.</FieldDescription>
            </FieldContent>
            <InputGroup>
              <InputGroupAddon>
                <InputGroupText>@</InputGroupText>
              </InputGroupAddon>
              <InputGroupInput id="email" />
              <InputGroupAddon align="inline-end">
                <InputGroupButton>Check</InputGroupButton>
              </InputGroupAddon>
            </InputGroup>
            <FieldError errors={[{ message: 'Required' }, { message: 'Required' }]} />
          </Field>
          <FieldSeparator>or</FieldSeparator>
          <Field>
            <InputGroupTextarea aria-label="Note" />
            <FieldError errors={[{ message: 'Too short' }, { message: 'Too plain' }]} />
          </Field>
        </FieldGroup>
      </FieldSet>,
    );

    expect(screen.getByRole('group', { name: 'Contact' }).tagName).toBe('FIELDSET');
    expect(screen.getByLabelText('Email').getAttribute('data-slot')).toBe('input-group-control');
    expect(screen.getAllByRole('alert').map((alert) => alert.textContent)).toEqual(['Required', 'Too shortToo plain']);
    expect(screen.getByRole('button', { name: 'Check' }).getAttribute('type')).toBe('button');
  });

  it('focuses the input when the addon is clicked', () => {
    render(
      <InputGroup>
        <InputGroupAddon>
          <InputGroupText>$</InputGroupText>
        </InputGroupAddon>
        <InputGroupInput aria-label="Price" />
      </InputGroup>,
    );

    fireEvent.click(screen.getByText('$'));

    expect(document.activeElement).toBe(screen.getByLabelText('Price'));
  });
});

describe('OTPField', () => {
  it('fills one slot per character and moves to the next slot', async () => {
    const complete = vi.fn();
    render(
      <>
        <Label htmlFor="code">Code</Label>
        <OTPField id="code" length={4} onValueComplete={complete}>
          <OTPFieldGroup>
            <OTPFieldSlot />
            <OTPFieldSlot aria-label="Character 2 of 4" />
          </OTPFieldGroup>
          <OTPFieldSeparator />
          <OTPFieldGroup>
            <OTPFieldSlot aria-label="Character 3 of 4" />
            <OTPFieldSlot aria-label="Character 4 of 4" />
          </OTPFieldGroup>
        </OTPField>
      </>,
    );
    // The first slot takes the field's own label.
    const first = screen.getByRole('textbox', { name: 'Code' });
    first.focus();

    fireEvent.change(first, { target: { value: '7' } });

    await waitFor(() => {
      expect(document.activeElement).toBe(screen.getByLabelText('Character 2 of 4'));
    });
    expect((first as HTMLInputElement).value).toBe('7');
    expect(screen.getByRole('separator').getAttribute('data-slot')).toBe('otp-field-separator');
  });
});
