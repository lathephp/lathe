import { router } from '@inertiajs/react';
import { __panel } from '@lathephp/core';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { useState } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { Modal } from '../components/Modal';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
  vi.useRealTimers();
  window.sessionStorage.clear();
});

function Nested({ depth, onClose }: { depth: number; onClose: () => void }) {
  const [child, setChild] = useState(false);

  return (
    <Modal labelledBy={`title-${String(depth)}`} onClose={onClose}>
      <h2 id={`title-${String(depth)}`}>{`Modal ${String(depth)}`}</h2>
      <button
        type="button"
        onClick={() => {
          setChild(true);
        }}
      >
        {`Open ${String(depth + 1)}`}
      </button>
      {child ? (
        <Nested
          depth={depth + 1}
          onClose={() => {
            setChild(false);
          }}
        />
      ) : null}
    </Modal>
  );
}

function Opener() {
  const [open, setOpen] = useState(false);

  return (
    <>
      <button
        type="button"
        onClick={() => {
          setOpen(true);
        }}
      >
        Opener
      </button>
      {open ? (
        <Nested
          depth={1}
          onClose={() => {
            setOpen(false);
          }}
        />
      ) : null}
    </>
  );
}

describe('modal stack', () => {
  it('pauses shortcuts per modal, closes the top one on Escape, locks scroll and returns focus', () => {
    render(<Opener />);
    const opener = screen.getByRole('button', { name: 'Opener' });
    opener.focus();

    fireEvent.click(opener);
    fireEvent.click(screen.getByRole('button', { name: 'Open 2' }));

    expect(__panel.shortcutPauseCount()).toBe(2);
    expect(document.body.style.overflow).toBe('hidden');
    expect(screen.getAllByRole('dialog')).toHaveLength(2);

    fireEvent.keyDown(document, { key: 'Escape' });
    expect(screen.getAllByRole('dialog')).toHaveLength(1);
    expect(__panel.shortcutPauseCount()).toBe(1);

    fireEvent.keyDown(document, { key: 'Escape' });
    expect(screen.queryAllByRole('dialog')).toHaveLength(0);
    expect(__panel.shortcutPauseCount()).toBe(0);
    expect(document.body.style.overflow).toBe('');
    expect(document.activeElement).toBe(opener);
  });

  it('keeps focus inside the top modal', () => {
    render(<Opener />);
    fireEvent.click(screen.getByRole('button', { name: 'Opener' }));
    const only = screen.getByRole('button', { name: 'Open 2' });

    expect(document.activeElement).toBe(only);
    fireEvent.keyDown(document, { key: 'Tab' });
    expect(document.activeElement).toBe(only);
  });

  it('opens at most three modals', () => {
    render(<Opener />);
    fireEvent.click(screen.getByRole('button', { name: 'Opener' }));
    fireEvent.click(screen.getByRole('button', { name: 'Open 2' }));
    fireEvent.click(screen.getByRole('button', { name: 'Open 3' }));
    fireEvent.click(screen.getByRole('button', { name: 'Open 4' }));

    expect(screen.getAllByRole('dialog')).toHaveLength(3);
    fireEvent.keyDown(document, { key: 'Escape' });
    fireEvent.keyDown(document, { key: 'Escape' });
    fireEvent.keyDown(document, { key: 'Escape' });
    expect(__panel.shortcutPauseCount()).toBe(0);
  });
});

const field = (key: string, value: unknown) => ({
  key,
  attribute: key,
  label: key === 'name' ? 'Name' : 'Country',
  component: 'text-field',
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
  value,
});

function renderForm() {
  return renderPage('Resources/Form', {
    resource: { uriKey: 'customers', label: 'Customers', singularLabel: 'Customer', abilities: { create: true } },
    mode: 'create',
    id: null,
    title: 'Create Customer',
    breadcrumbs: [],
    form: {
      fields: [field('name', ''), field('country', 'US')],
      layout: [{ type: 'panel', attribute: 'details', name: 'Details', fields: ['name', 'country'], toolbar: true, collapsible: false, collapsed: false, hideHeading: false, headingInCard: false, help: null, limit: null, columns: null, columnSpan: null, borderless: false }],
    },
    submitLabel: 'Create Customer',
  });
}

function json(status: number, body: unknown) {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
}

describe('re-auth modal', () => {
  it('opens on 419 with the email filled, signs in and replays the submit once with the typed values', async () => {
    const calls: string[] = [];
    const fetch = vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
      const url = typeof input === 'string' ? input : '';
      calls.push(url);

      if (url.endsWith('/login')) return Promise.resolve(json(200, { two_factor: false }));

      return Promise.resolve(calls.filter((call) => call.endsWith('/customers')).length === 1 ? json(419, { message: 'CSRF token mismatch.' }) : json(201, { id: 3, redirect: 'customers/3' }));
    });
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderForm();

    fireEvent.change(screen.getByLabelText(/Name/), { target: { value: 'Ann' } });
    fireEvent.click(screen.getByRole('button', { name: 'Create Customer' }));

    const email = await screen.findByLabelText('Email');
    expect((email as HTMLInputElement).value).toBe('ada@example.com');
    fireEvent.change(screen.getByLabelText('Password'), { target: { value: 'secret' } });
    fireEvent.click(screen.getByRole('button', { name: 'Sign in' }));

    await waitFor(() => {
      expect(visit).toHaveBeenCalledWith('/lathe/resources/customers/3');
    });
    expect(calls).toEqual(['/lathe/api/resources/customers', '/lathe/login', '/lathe/api/resources/customers']);
    const replay = fetch.mock.calls[2]?.[1];
    expect(JSON.parse(typeof replay?.body === 'string' ? replay.body : '{}')).toEqual({ name: 'Ann', country: 'US' });
  });

  it('cancels the replay and reloads when another user signs in', async () => {
    vi.spyOn(globalThis, 'fetch').mockImplementation((input) =>
      Promise.resolve(typeof input === 'string' && input.endsWith('/login') ? json(200, {}) : json(401, { message: 'Unauthenticated.' })),
    );
    const reload = vi.spyOn(router, 'reload').mockImplementation(() => undefined);
    renderForm();

    fireEvent.click(screen.getByRole('button', { name: 'Create Customer' }));
    fireEvent.change(await screen.findByLabelText('Email'), { target: { value: 'grace@example.com' } });
    fireEvent.click(screen.getByRole('button', { name: 'Sign in' }));

    await waitFor(() => {
      expect(reload).toHaveBeenCalled();
    });
    expect(screen.queryByRole('dialog')).toBeNull();
  });
});

describe('form draft', () => {
  const key = () => `lathe-draft:${window.location.pathname}`;

  it('saves typed values 500 ms after the last change and restores them with a toast', () => {
    vi.useFakeTimers();
    const first = renderForm();

    fireEvent.change(screen.getByLabelText(/Name/), { target: { value: 'Ann' } });
    act(() => {
      vi.advanceTimersByTime(499);
    });
    expect(window.sessionStorage.getItem(key())).toBeNull();
    act(() => {
      vi.advanceTimersByTime(1);
    });
    expect(JSON.parse(window.sessionStorage.getItem(key()) ?? '{}')).toEqual({ name: 'Ann', country: 'US' });

    first.unmount();
    renderForm();

    expect(screen.getByLabelText<HTMLInputElement>(/Name/).value).toBe('Ann');
    expect(__panel.toasts.get().map((toast) => toast.message)).toContain('Draft restored');

    fireEvent.click(screen.getByRole('button', { name: 'Discard draft' }));
    expect(window.sessionStorage.getItem(key())).toBeNull();
    expect(screen.getByLabelText<HTMLInputElement>(/Name/).value).toBe('');
  });

  it('clears the draft after a successful save', async () => {
    window.sessionStorage.setItem(key(), JSON.stringify({ name: 'Ann' }));
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(json(201, { id: 3, redirect: 'customers/3' }));
    vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderForm();

    fireEvent.click(screen.getByRole('button', { name: 'Create Customer' }));

    await waitFor(() => {
      expect(window.sessionStorage.getItem(key())).toBeNull();
    });
  });
});
