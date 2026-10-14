// @vitest-environment jsdom
import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { __panel, ActionDropdown, AppLayout, Excerpt } from '../index';

afterEach(cleanup);

describe('the component proxies', () => {
  it('render the component the panel registered, with the props given', async () => {
    __panel.registerComponents({
      ActionDropdown: ({ uriKey, id, actions }) => <p>{`${uriKey} ${String(id)} ${String(actions.length)}`}</p>,
      AppLayout: ({ children }) => <main>{children}</main>,
    });

    render(
      <AppLayout>
        <ActionDropdown uriKey="orders" id={5} actions={[]} />
      </AppLayout>,
    );

    expect((await screen.findByRole('main')).textContent).toBe('orders 5 0');
  });

  it('throw a clear error outside the panel', () => {
    vi.spyOn(console, 'error').mockImplementation(() => undefined);

    expect(() => render(<Excerpt lines={1}>Text</Excerpt>)).toThrow('Excerpt renders only inside the Lathe panel.');
  });
});
