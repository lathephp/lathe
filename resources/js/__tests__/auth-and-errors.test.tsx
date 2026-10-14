import { router } from '@inertiajs/react';
import { __panel } from '@lathephp/core';
import { fireEvent, screen, within } from '@testing-library/react';
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

  it('links to Forgot password when reset routes are on', () => {
    renderPage('Auth/Login', { username: 'email', errors: {} }, {
      shared: { ...signedOut, authPaths: { login: '/lathe/login', logout: '/lathe/logout', forgotPassword: '/forgot', resetPassword: '/reset' } },
      url: '/lathe/login',
    });

    expect(screen.getByRole('link', { name: 'Forgot your password?' }).getAttribute('href')).toBe('/forgot');
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

describe('error page for a bad address', () => {
  it('shows the reason for a 422 inside the shell', () => {
    renderPage('Error', { status: 422, message: 'Narrow the filters', shell: true });

    expect(within(screen.getByRole('main')).getByText('Narrow the filters')).toBeTruthy();
    expect(within(screen.getByRole('main')).getByRole('heading', { name: 'This page cannot be shown' })).toBeTruthy();
  });
});

describe('error page without the panel props', () => {
  it('draws full screen when the failure came before the shared props, as a CSRF mismatch does', () => {
    renderPage(
      'Error',
      { status: 419, message: null, shell: false },
      { shared: { auth: undefined, basePath: undefined, navigation: undefined, layout: undefined } },
    );

    expect(screen.getByRole('heading', { name: 'Your session expired' })).toBeTruthy();
    expect(screen.getByRole('link', { name: 'Back to dashboard' }).getAttribute('href')).toBe('/lathe');
  });
});

describe('forgot password', () => {
  function renderForgot(props: { status?: string | null; errors?: Record<string, string> } = {}) {
    return renderPage('Auth/ForgotPassword', { status: props.status ?? null, errors: props.errors ?? {} }, { shared: signedOut, url: '/lathe/forgot-password' });
  }

  it('asks for the email in the auth card and posts it', () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderForgot();

    expect(screen.getByRole('heading', { level: 1 }).textContent).toBe('Acme');
    fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'ada@example.com' } });
    fireEvent.click(screen.getByRole('button', { name: 'Email password reset link' }));

    expect(visit).toHaveBeenCalledWith('/lathe/forgot-password', expect.objectContaining({ method: 'post', data: { email: 'ada@example.com' } }));
    expect(screen.getByRole('link', { name: 'Back to sign in' }).getAttribute('href')).toBe('/lathe/login');
  });

  it('shows the answer as a status and an error under the field', () => {
    renderForgot({ status: 'We have emailed your password reset link.', errors: { email: 'The email field must be a valid email address.' } });

    expect(screen.getByRole('status').textContent).toBe('We have emailed your password reset link.');
    expect(screen.getByLabelText('Email').getAttribute('aria-describedby')).toBe(screen.getByText('The email field must be a valid email address.').id);
  });
});

describe('reset password', () => {
  function renderReset(errors: Record<string, string> = {}) {
    return renderPage('Auth/ResetPassword', { token: 'abc', email: 'ada@example.com', errors }, { shared: signedOut, url: '/lathe/reset-password/abc' });
  }

  it('shows the email read-only and posts the token with the new password', () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderReset();

    const email = screen.getByLabelText<HTMLInputElement>('Email');
    expect(email.readOnly).toBe(true);
    expect(email.value).toBe('ada@example.com');

    fireEvent.change(screen.getByLabelText('New password'), { target: { value: 'new-password-2026' } });
    fireEvent.change(screen.getByLabelText('Confirm password'), { target: { value: 'new-password-2026' } });
    fireEvent.click(screen.getByRole('button', { name: 'Reset password' }));

    expect(visit).toHaveBeenCalledWith(
      '/lathe/reset-password',
      expect.objectContaining({ method: 'post', data: { token: 'abc', email: 'ada@example.com', password: 'new-password-2026', password_confirmation: 'new-password-2026' } }),
    );
  });

  it('links back to Forgot password under an expired token error', () => {
    renderPage(
      'Auth/ResetPassword',
      { token: 'abc', email: 'ada@example.com', errors: { email: 'This password reset token is invalid.' } },
      { shared: { ...signedOut, authPaths: { login: '/lathe/login', logout: '/lathe/logout', forgotPassword: '/lathe/forgot-password', resetPassword: '/lathe/reset-password' } }, url: '/lathe/reset-password/abc' },
    );

    expect(screen.getByText('This password reset token is invalid.')).toBeTruthy();
    expect(screen.getByRole('link', { name: 'Send a new reset link' }).getAttribute('href')).toBe('/lathe/forgot-password');
  });

  it('says on the login screen that the password was reset', () => {
    renderPage('Auth/Login', { username: 'email', status: 'Your password has been reset.', errors: {} }, { shared: signedOut, url: '/lathe/login' });

    expect(screen.getByRole('status').textContent).toBe('Your password has been reset.');
  });
});

