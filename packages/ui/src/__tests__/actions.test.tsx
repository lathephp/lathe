// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { ButtonGroup, ButtonGroupSeparator, ButtonGroupText } from '../button-group';
import { Badge, Button, Kbd, KbdGroup, Spinner } from '../index';
import { Toggle } from '../toggle';
import { ToggleGroup, ToggleGroupItem } from '../toggle-group';

afterEach(cleanup);

describe('Button', () => {
  it('renders a button that does not submit a form, in the variant and size asked', () => {
    const submit = vi.fn((event: Event) => {
      event.preventDefault();
    });
    render(
      <form
        onSubmit={(event) => {
          submit(event.nativeEvent);
        }}
      >
        <Button variant="outline" size="sm">
          Save
        </Button>
      </form>,
    );
    const button = screen.getByRole('button', { name: 'Save' });

    fireEvent.click(button);

    expect(button.getAttribute('type')).toBe('button');
    expect(button.className).toContain('cn-button-variant-outline');
    expect(button.className).toContain('cn-button-size-sm');
    expect(submit).not.toHaveBeenCalled();
  });

  it('renders the element given to render in place of the button', () => {
    render(
      <Button
        nativeButton={false}
        // eslint-disable-next-line jsx-a11y/anchor-has-content -- the component passes its children to the anchor.
        render={<a href="/lathe/orders" />}
      >
        Orders
      </Button>,
    );

    const link = screen.getByRole('button', { name: 'Orders' });
    expect(link.tagName).toBe('A');
    expect(link.getAttribute('href')).toBe('/lathe/orders');
    expect(link.className).toContain('cn-button-variant-default');
  });
});

describe('Badge, Kbd and Spinner', () => {
  it('render a badge as a span, or as the element given to render', () => {
    render(
      <>
        <Badge variant="secondary">Draft</Badge>
        {/* eslint-disable-next-line jsx-a11y/anchor-has-content -- the component passes its children to the anchor. */}
        <Badge render={<a href="/lathe/tags/new" />}>New</Badge>
      </>,
    );

    expect(screen.getByText('Draft').tagName).toBe('SPAN');
    expect(screen.getByText('Draft').className).toContain('cn-badge-variant-secondary');
    expect(screen.getByRole('link', { name: 'New' }).getAttribute('href')).toBe('/lathe/tags/new');
  });

  it('render keys and a labelled spinner', () => {
    render(
      <>
        <KbdGroup>
          <Kbd>Ctrl</Kbd>
          <Kbd>K</Kbd>
        </KbdGroup>
        <Spinner />
        <Spinner aria-label="Saving" />
      </>,
    );

    expect(screen.getByText('Ctrl').tagName).toBe('KBD');
    expect(screen.getAllByRole('status').map((status) => status.getAttribute('aria-label'))).toEqual([
      'Loading',
      'Saving',
    ]);
  });
});

describe('ButtonGroup', () => {
  it('groups buttons with text and a separator', () => {
    render(
      <ButtonGroup aria-label="Pages" orientation="vertical">
        <Button>Prev</Button>
        <ButtonGroupSeparator />
        <ButtonGroupText render={<span />}>1 of 3</ButtonGroupText>
      </ButtonGroup>,
    );

    const group = screen.getByRole('group', { name: 'Pages' });
    expect(group.getAttribute('data-orientation')).toBe('vertical');
    expect(screen.getByText('1 of 3').tagName).toBe('SPAN');
    expect(screen.getByRole('separator').getAttribute('aria-orientation')).toBe('vertical');
  });
});

describe('Toggle and ToggleGroup', () => {
  it('press a toggle on click', () => {
    render(<Toggle aria-label="Bold">B</Toggle>);
    const toggle = screen.getByRole('button', { name: 'Bold' });

    fireEvent.click(toggle);

    expect(toggle.getAttribute('aria-pressed')).toBe('true');
  });

  it('moves focus between the items with the arrow keys', async () => {
    render(
      <ToggleGroup aria-label="Align" defaultValue={['left']}>
        <ToggleGroupItem value="left">Left</ToggleGroupItem>
        <ToggleGroupItem value="right">Right</ToggleGroupItem>
      </ToggleGroup>,
    );
    const left = screen.getByRole('button', { name: 'Left' });
    left.focus();

    fireEvent.keyDown(left, { key: 'ArrowRight' });

    await waitFor(() => {
      expect(document.activeElement).toBe(screen.getByRole('button', { name: 'Right' }));
    });
    expect(left.getAttribute('aria-pressed')).toBe('true');
  });
});
