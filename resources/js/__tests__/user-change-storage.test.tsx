import { __panel } from '@lathephp/core';
import { act, fireEvent, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { followUser } from '../lib/session';
import { renderPage, sharedProps } from './support';

afterEach(() => {
  vi.useRealTimers();
  window.sessionStorage.clear();
  window.localStorage.clear();
});

const recentsKey = 'lathe-recent-searches';
const recent = { resourceTitle: 'Customers', title: 'Ann Taylor', subTitle: null, resourceName: 'customers', resourceId: 12, avatar: null, rounded: false, url: '/resources/customers/12' };

const field = (key: string, label: string, component: string) => ({
  key,
  attribute: key,
  label,
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
  value: '',
});

const user = (email: string | null) => ({ auth: { user: email === null ? null : { name: email, email } } });

function renderUserForm(email: string | null = 'admin@lathephp.com', name = email ?? '') {
  return renderPage(
    'Resources/Form',
    {
      resource: { uriKey: 'users', label: 'Users', singularLabel: 'User', abilities: { create: true } },
      mode: 'create',
      id: null,
      title: 'Create User',
      breadcrumbs: [],
      form: {
        fields: [field('name', 'Name', 'text-field'), field('password', 'Password', 'password-field'), field('password_confirmation', 'Confirm password', 'password-field')],
        layout: [{ type: 'panel', attribute: 'details', name: 'Details', fields: ['name', 'password', 'password_confirmation'], toolbar: true, collapsible: false, collapsed: false, hideHeading: false, headingInCard: false, help: null, limit: null, columns: null, columnSpan: null, borderless: false }],
      },
      submitLabel: 'Create User',
    },
    // An app that signs in by username sends a user with no email, which the type does not name.
    { shared: sharedProps({ auth: { user: { name, email: email as string } } }) },
  );
}

function typeNameAndPassword() {
  vi.useFakeTimers();
  fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Rubel' } });
  fireEvent.change(screen.getByLabelText(/^Password/), { target: { value: 's3cret-pass' } });
  fireEvent.change(screen.getByLabelText(/^Confirm password/), { target: { value: 's3cret-pass' } });
  act(() => {
    vi.advanceTimersByTime(1000);
  });
  vi.useRealTimers();
}

const draftKeys = () => Object.keys(window.sessionStorage).filter((key) => key.startsWith('lathe-draft:'));

describe('a form draft', () => {
  it('keeps no password field, and restores the other fields with Password empty', () => {
    followUser(user('admin@lathephp.com'));
    const first = renderUserForm();

    typeNameAndPassword();

    expect(draftKeys()).toHaveLength(1);
    expect(Object.values(window.sessionStorage).some((value) => String(value).includes('s3cret-pass'))).toBe(false);

    first.unmount();
    renderUserForm();

    expect(__panel.toasts.get().map((toast) => toast.message)).toContain('Draft restored');
    expect(screen.getByLabelText<HTMLInputElement>(/^Name/).value).toBe('Rubel');
    expect(screen.getByLabelText<HTMLInputElement>(/^Password/).value).toBe('');
  });

  it('keeps no token field, a secret like a password', async () => {
    followUser(user('admin@lathephp.com'));
    renderPage(
      'Resources/Form',
      {
        resource: { uriKey: 'users', label: 'Users', singularLabel: 'User', abilities: { create: true } },
        mode: 'create',
        id: null,
        title: 'Create User',
        breadcrumbs: [],
        form: {
          fields: [field('name', 'Name', 'text-field'), field('api_key', 'API key', 'token-field')],
          layout: [{ type: 'panel', attribute: 'details', name: 'Details', fields: ['name', 'api_key'], toolbar: true, collapsible: false, collapsed: false, hideHeading: false, headingInCard: false, help: null, limit: null, columns: null, columnSpan: null, borderless: false }],
        },
        submitLabel: 'Create User',
      },
      { shared: sharedProps({ auth: { user: { name: 'admin@lathephp.com', email: 'admin@lathephp.com' } } }) },
    );
    const key = await screen.findByLabelText(/^API key/);

    vi.useFakeTimers();
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Rubel' } });
    fireEvent.change(key, { target: { value: 'sk-live-4821' } });
    act(() => {
      vi.advanceTimersByTime(1000);
    });
    vi.useRealTimers();

    expect(draftKeys()).toHaveLength(1);
    expect(Object.values(window.sessionStorage).some((value) => String(value).includes('sk-live-4821'))).toBe(false);
  });

  it('is gone once the signed-in user signs out, so the next user opens the form clean', () => {
    followUser(user('admin@lathephp.com'));
    const first = renderUserForm();
    typeNameAndPassword();
    first.unmount();

    followUser(user(null));
    followUser(user('editor@lathephp.com'));

    expect(draftKeys()).toEqual([]);
    renderUserForm('editor@lathephp.com');
    expect(screen.getByLabelText<HTMLInputElement>(/^Name/).value).toBe('');
  });

  it('is gone when an impersonation starts or stops', () => {
    followUser(user('admin@lathephp.com'));
    const first = renderUserForm();
    typeNameAndPassword();
    first.unmount();

    followUser(user('editor@lathephp.com'));
    expect(draftKeys()).toEqual([]);

    const second = renderUserForm('editor@lathephp.com');
    typeNameAndPassword();
    second.unmount();

    followUser(user('admin@lathephp.com'));
    expect(draftKeys()).toEqual([]);
  });

  it('never restores for another user, even when the form is the first page that user loads', () => {
    followUser(user('admin@lathephp.com'));
    const first = renderUserForm();
    typeNameAndPassword();
    first.unmount();

    renderUserForm('editor@lathephp.com');

    expect(screen.getByLabelText<HTMLInputElement>(/^Name/).value).toBe('');
    expect(draftKeys()).toEqual([]);
  });

  it('keeps them for a signed-in user who has no email, as an app that signs in by username', () => {
    const noEmail = { auth: { user: { name: 'rubel', email: null } } };
    followUser(noEmail);
    const first = renderUserForm(null, 'rubel');
    typeNameAndPassword();
    first.unmount();
    window.localStorage.setItem(recentsKey, JSON.stringify([recent]));

    followUser(noEmail);

    expect(draftKeys()).toHaveLength(1);
    expect(window.localStorage.getItem(recentsKey)).toBe(JSON.stringify([recent]));

    followUser(user(null));
    expect(draftKeys()).toEqual([]);
  });

  it('still restores for the same user after a full reload, with the recent searches', async () => {
    followUser(user('admin@lathephp.com'));
    const first = renderUserForm();
    typeNameAndPassword();
    first.unmount();
    window.localStorage.setItem(recentsKey, JSON.stringify([recent]));

    // A reload starts the session module afresh, with only what the browser stored.
    vi.resetModules();
    const fresh = await import('../lib/session');
    fresh.followUser(user('admin@lathephp.com'));

    expect(draftKeys()).toHaveLength(1);
    expect(window.localStorage.getItem(recentsKey)).toBe(JSON.stringify([recent]));
  });

  it('is gone after a full reload that lands on another user, with the recent searches', async () => {
    followUser(user('admin@lathephp.com'));
    const first = renderUserForm();
    typeNameAndPassword();
    first.unmount();
    window.localStorage.setItem(recentsKey, JSON.stringify([recent]));

    vi.resetModules();
    const fresh = await import('../lib/session');
    fresh.followUser(user('editor@lathephp.com'));

    expect(draftKeys()).toEqual([]);
    expect(window.localStorage.getItem(recentsKey)).toBeNull();
  });
});
