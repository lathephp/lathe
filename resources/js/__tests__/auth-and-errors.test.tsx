import { router } from '@inertiajs/react';
import { __panel } from '@lathephp/core';
import { fireEvent, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { bracketed, renderPage, visibleStrings } from './support';

const signedOut = { auth: { user: null } };

afterEach(() => {
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
});

function renderLogin(errors: Record<string, string> = {}) {
  return renderPage('Auth/Login', { username: 'email', errors }, { shared: signedOut, url: '/lathe/login' });
}

describe('login', () => {
  it('has the email, password and remember fields and a sign in button', () => {
    renderLogin();

    const email = screen.getByLabelText<HTMLInputElement>('Email');
    const password = screen.getByLabelText<HTMLInputElement>('Password');
    const remember = screen.getByLabelText<HTMLInputElement>('Remember me');

    expect(email.type).toBe('email');
    expect(email.autocomplete).toBe('username');
    expect(password.type).toBe('password');
    expect(remember.type).toBe('checkbox');
    expect(screen.getByRole('button', { name: 'Sign in' })).toBeTruthy();
    expect(screen.getByRole('heading', { level: 1 }).textContent).toBe('Acme');
  });

  it('has no forgot password link and no sidebar', () => {
    renderLogin();

    expect(screen.queryByText('Forgot your password?')).toBeNull();
    expect(screen.queryByRole('complementary')).toBeNull();
  });

  it('shows a validation error under its field', () => {
    renderLogin({ email: 'These credentials do not match our records.' });

    const email = screen.getByLabelText('Email');
    const error = screen.getByText('These credentials do not match our records.');

    expect(email.getAttribute('aria-invalid')).toBe('true');
    expect(email.getAttribute('aria-describedby')).toBe(error.id);
    expect(email.compareDocumentPosition(error) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    expect(screen.getByLabelText('Password').getAttribute('aria-invalid')).toBeNull();
  });

  it('posts the email, password and remember flag to the login route', () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderLogin();

    fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'ada@example.com' } });
    fireEvent.change(screen.getByLabelText('Password'), { target: { value: 'secret' } });
    fireEvent.click(screen.getByLabelText('Remember me'));
    fireEvent.click(screen.getByRole('button', { name: 'Sign in' }));

    expect(visit).toHaveBeenCalledOnce();
    expect(visit.mock.calls[0]?.[0]).toBe('/lathe/login');
    expect(visit.mock.calls[0]?.[1]).toMatchObject({
      method: 'post',
      data: { email: 'ada@example.com', password: 'secret', remember: true },
    });
  });

  it('sends every string through the translator', () => {
    __panel.setTranslator(bracketed);
    renderLogin({ email: 'These credentials do not match our records.' });

    const data = new Set(['Acme', 'These credentials do not match our records.']);
    const strings = visibleStrings();

    expect(strings).toContain('[Sign in]');
    expect(strings.filter((text) => !data.has(text) && !/^\[.+\]$/.test(text))).toEqual([]);
  });
});

function renderError(status: number, shell: boolean, message: string | null = null, shared = {}) {
  return renderPage('Error', { status, message, shell }, { shared, url: '/lathe/resources/nothing' });
}

describe('error page', () => {
  it('shows a 403 inside the shell with the server message and a sign out button', () => {
    const post = vi.spyOn(router, 'post').mockImplementation(() => undefined);
    renderError(403, true, 'Lathe::auth() denies everyone. Call ->allowAll() to let every user in.');

    expect(screen.getByRole('complementary')).toBeTruthy();
    expect(screen.getByRole('heading', { level: 1 }).textContent).toBe('Forbidden');
    expect(screen.getByText('Lathe::auth() denies everyone. Call ->allowAll() to let every user in.')).toBeTruthy();

    fireEvent.click(screen.getByRole('button', { name: 'Sign out' }));

    expect(post).toHaveBeenCalledWith('/lathe/logout');
  });

  it('falls back to its own text when a 403 has no message', () => {
    renderError(403, true);

    expect(screen.getByText('You do not have permission to open this page.')).toBeTruthy();
  });

  it('shows a 404 inside the shell with a link back to the dashboard', () => {
    renderError(404, true);

    expect(screen.getByRole('complementary')).toBeTruthy();
    expect(screen.getByRole('heading', { level: 1 }).textContent).toBe('Page not found');
    expect(screen.getByRole('link', { name: 'Back to dashboard' }).getAttribute('href')).toBe('/lathe');
  });

  it('shows a 404 full screen without the shell', () => {
    renderError(404, false, null, signedOut);

    expect(screen.queryByRole('complementary')).toBeNull();
    expect(screen.getByRole('main')).toBeTruthy();
    expect(screen.getByRole('heading', { level: 1 }).textContent).toBe('Page not found');
    expect(screen.getByRole('link', { name: 'Back to dashboard' }).getAttribute('href')).toBe('/lathe');
    expect(screen.queryByRole('button', { name: 'Sign out' })).toBeNull();
  });

  it('offers a reload when the session expired', () => {
    const reload = vi.fn();
    // Inertia's Link reads the location, so the stub keeps the URL parts.
    vi.stubGlobal('location', Object.assign(new URL(window.location.href), { reload }));
    renderError(419, false, null, signedOut);

    expect(screen.getByRole('heading', { level: 1 }).textContent).toBe('Your session expired');

    fireEvent.click(screen.getByRole('button', { name: 'Reload' }));

    expect(reload).toHaveBeenCalledOnce();
  });

  it.each([
    [500, 'Server error'],
    [503, 'Service unavailable'],
  ])('shows a %i inside the shell or full screen', (status, title) => {
    const { unmount } = renderError(status, true);

    expect(screen.getByRole('complementary')).toBeTruthy();
    expect(screen.getByRole('heading', { level: 1 }).textContent).toBe(title);
    unmount();

    renderError(status, false);

    expect(screen.queryByRole('complementary')).toBeNull();
    expect(screen.getByRole('heading', { level: 1 }).textContent).toBe(title);
  });

  it('sends every string through the translator', () => {
    __panel.setTranslator(bracketed);
    renderError(419, false, null, signedOut);

    // The status code is a number, not text.
    expect(visibleStrings().filter((text) => text !== '419' && !/^\[.+\]$/.test(text))).toEqual([]);
  });
});
