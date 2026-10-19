import { router } from '@inertiajs/react';
import { __panel, addShortcut, ApiError, request } from '@lathephp/core';
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { PasswordConfirmHost } from '../components/PasswordConfirmHost';
import { bracketed, renderPage, visibleStrings } from './support';

afterEach(() => {
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
  __panel.setPasswordConfirmer(undefined);
});

/** fetch answers these in turn, one per call. */
function respondInTurn(...answers: [number, string][]): void {
  const queue = [...answers];

  vi.stubGlobal(
    'fetch',
    vi.fn(() => {
      const [status, body] = queue.shift() ?? [500, ''];

      return Promise.resolve(new Response(body, { status }));
    }),
  );
}

function sent(): [unknown, unknown, unknown][] {
  return vi.mocked(fetch).mock.calls.map(([url, init]) => [url, init?.method, init?.body]);
}

const required: [number, string] = [423, '{"message":"Password confirmation required."}'];

/** The open dialog, once the modal's effect has moved focus in, paused the shortcuts and started listening for Escape. */
async function openDialog(): Promise<HTMLElement> {
  const dialog = await screen.findByRole('dialog', { name: 'Confirm your password' });
  await waitFor(() => {
    expect(dialog.contains(document.activeElement)).toBe(true);
  });

  return dialog;
}

describe('confirm password dialog', () => {
  it('opens on a 423, and the right password repeats the request once with its result', async () => {
    respondInTurn(required, [201, '""'], [200, '{"codes":["a1b2"]}']);
    render(<PasswordConfirmHost />);

    const result = request('/lathe/api/thing', { method: 'POST', body: { enable: true } });
    const dialog = await screen.findByRole('dialog', { name: 'Confirm your password' });

    fireEvent.change(screen.getByLabelText('Password'), { target: { value: 'secret' } });
    fireEvent.click(screen.getByRole('button', { name: 'Confirm' }));

    expect(await result).toEqual({ codes: ['a1b2'] });
    expect(sent()).toEqual([
      ['/lathe/api/thing', 'POST', '{"enable":true}'],
      ['/lathe/confirm-password', 'POST', '{"password":"secret"}'],
      ['/lathe/api/thing', 'POST', '{"enable":true}'],
    ]);
    await waitFor(() => {
      expect(dialog.isConnected).toBe(false);
    });
  });

  it('shows a wrong password error in the dialog and keeps it open', async () => {
    respondInTurn(required, [422, '{"message":"x","errors":{"password":["The provided password was incorrect."]}}']);
    render(<PasswordConfirmHost />);

    void request('/lathe/api/thing').catch(() => undefined);
    await screen.findByRole('dialog', { name: 'Confirm your password' });

    fireEvent.change(screen.getByLabelText('Password'), { target: { value: 'wrong' } });
    screen.getByRole('button', { name: 'Confirm' }).focus();
    fireEvent.click(screen.getByRole('button', { name: 'Confirm' }));

    const error = await screen.findByText('The provided password was incorrect.');
    const password = screen.getByLabelText('Password');

    expect(password.getAttribute('aria-invalid')).toBe('true');
    expect(password.getAttribute('aria-describedby')).toBe(error.id);
    // The Confirm button was disabled while it waited, so the field takes the focus back for another try.
    expect(document.activeElement).toBe(password);
    expect(screen.getByRole('dialog', { name: 'Confirm your password' })).toBeTruthy();
    expect(sent()).toHaveLength(2);
  });

  it('closes on Escape and rejects with the 423, sending nothing again', async () => {
    respondInTurn(required, [200, '{}']);
    render(<PasswordConfirmHost />);

    const result = request('/lathe/api/thing').catch((e: unknown) => e);
    await openDialog();

    fireEvent.keyDown(document, { key: 'Escape' });

    const error = await result;
    expect(error).toBeInstanceOf(ApiError);
    expect(error).toMatchObject({ status: 423 });
    expect(screen.queryByRole('dialog')).toBeNull();
    expect(sent()).toEqual([['/lathe/api/thing', 'GET', undefined]]);
  });

  it('closes from its Close button like Cancel, rejecting with the 423', async () => {
    respondInTurn(required, [200, '{}']);
    render(<PasswordConfirmHost />);

    const result = request('/lathe/api/thing').catch((e: unknown) => e);
    const dialog = await openDialog();

    expect(document.activeElement).toBe(screen.getByLabelText('Password'));
    fireEvent.click(within(dialog).getByRole('button', { name: 'Close' }));

    expect(await result).toMatchObject({ status: 423 });
    expect(screen.queryByRole('dialog')).toBeNull();
  });

  it('keeps page shortcuts from firing while it is open', async () => {
    respondInTurn(required);
    const create = vi.fn();
    const remove = addShortcut('c', create);
    render(<PasswordConfirmHost />);

    void request('/lathe/api/thing').catch(() => undefined);
    await openDialog();

    fireEvent.keyDown(document.body, { key: 'c' });

    expect(create).not.toHaveBeenCalled();
    remove();
  });

  it('opens one dialog for two requests that need confirming, and repeats both', async () => {
    respondInTurn(required, required, [201, '""'], [200, '{"n":1}'], [200, '{"n":2}']);
    render(<PasswordConfirmHost />);

    const first = request('/lathe/api/one');
    const second = request('/lathe/api/two');
    await waitFor(() => {
      expect(vi.mocked(fetch)).toHaveBeenCalledTimes(2);
    });

    expect(await screen.findAllByRole('dialog')).toHaveLength(1);
    fireEvent.change(screen.getByLabelText('Password'), { target: { value: 'secret' } });
    fireEvent.click(screen.getByRole('button', { name: 'Confirm' }));

    expect(await Promise.all([first, second])).toEqual([{ n: 1 }, { n: 2 }]);
  });

  it('sends every string through the translator', async () => {
    __panel.setTranslator(bracketed);
    respondInTurn(required);
    render(<PasswordConfirmHost />);

    void request('/lathe/api/thing').catch(() => undefined);
    await screen.findByRole('dialog');

    for (const text of visibleStrings(screen.getByRole('dialog'))) {
      expect(text).toMatch(/^\[.*\]$/);
    }
  });
});

describe('confirm password page', () => {
  function renderConfirm(errors: Record<string, string> = {}) {
    return renderPage('Auth/ConfirmPassword', { errors }, { url: '/lathe/confirm-password' });
  }

  it('posts the password to the confirm route, which sends the browser on to the page it wanted', () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderConfirm();

    expect(screen.getByLabelText<HTMLInputElement>('Password').autocomplete).toBe('current-password');
    fireEvent.change(screen.getByLabelText('Password'), { target: { value: 'secret' } });
    fireEvent.click(screen.getByRole('button', { name: 'Confirm' }));

    expect(visit).toHaveBeenCalledOnce();
    expect(visit.mock.calls[0]?.[0]).toBe('/lathe/confirm-password');
    expect(visit.mock.calls[0]?.[1]).toMatchObject({ method: 'post', data: { password: 'secret' } });
  });

  it('shows a wrong password error under the field', () => {
    renderConfirm({ password: 'The provided password was incorrect.' });

    const password = screen.getByLabelText('Password');
    const error = screen.getByText('The provided password was incorrect.');

    expect(password.getAttribute('aria-invalid')).toBe('true');
    expect(password.getAttribute('aria-describedby')).toBe(error.id);
  });

  it('sends every string through the translator', () => {
    __panel.setTranslator(bracketed);
    renderConfirm();

    for (const text of visibleStrings().filter((text) => text !== 'Acme')) {
      expect(text).toMatch(/^\[.*\]$/);
    }
  });
});
