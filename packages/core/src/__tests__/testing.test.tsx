// @vitest-environment jsdom
import { cleanup, render } from '@testing-library/react';
import { afterEach, expect, it } from 'vitest';
// A plugin's Vitest config aliases @lathephp/core to @lathephp/core/testing; this import stands for that.
import { mockLathe, useLathe, useTranslation } from '../testing';

function Greeting() {
  const { apiPath, auth } = useLathe();
  const { __ } = useTranslation();

  return <p>{`${__('Hello, :name', { name: auth?.user?.name ?? '' })} ${apiPath}`}</p>;
}

afterEach(() => {
  cleanup();
  mockLathe();
});

it('renders a plugin component with the mocked user, locale and paths', () => {
  mockLathe({
    basePath: '/admin',
    auth: { user: { name: 'Ada', email: 'ada@example.com' } },
    locale: 'fr',
    translations: { 'Hello, :name': 'Bonjour, :name' },
  });

  const { container } = render(<Greeting />);

  expect(container.textContent).toBe('Bonjour, Ada /admin/api');
});

it('falls back to the default panel path and English', () => {
  mockLathe({ auth: { user: { name: 'Grace', email: 'grace@example.com' } } });

  const { container } = render(<Greeting />);

  expect(container.textContent).toBe('Hello, Grace /lathe/api');
});
