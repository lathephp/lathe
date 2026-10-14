import { __panel } from '@lathephp/core';
import { fireEvent, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { FieldSchema } from '../fields/types';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
});

function schema(key: string, overrides: Partial<FieldSchema> = {}): FieldSchema {
  return {
    key,
    attribute: key,
    label: key.charAt(0).toUpperCase() + key.slice(1),
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
    ...overrides,
  };
}

const panel = (attribute: string, name: string, fields: string[]) => ({
  type: 'panel' as const,
  attribute,
  name,
  fields,
  toolbar: false,
  collapsible: false,
  collapsed: false,
  hideHeading: false,
  headingInCard: false,
  help: null,
  limit: null,
  columns: null,
  columnSpan: null,
  borderless: false,
});

const settings = {
  fields: [{ ...schema('greeting', { help: 'Shown at the top of the dashboard.' }), required: true }, schema('accent')],
  layout: [panel('details', 'Details', ['greeting']), panel('appearance', 'Appearance', ['accent'])],
  values: { greeting: 'Hi' },
};

function json(status: number, body: unknown) {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
}

/** Answers the settings GET with the form, and each PUT with the next given reply. */
function serve(...puts: Response[]) {
  return vi.spyOn(globalThis, 'fetch').mockImplementation((_input, init) =>
    Promise.resolve(init?.method === 'PUT' ? (puts.shift() ?? json(200, { saved: true })) : json(200, settings)),
  );
}

function renderSettings() {
  return renderPage('Plugin', { plugin: 'hello-plugin', title: 'Hello Plugin', path: 'settings' }, { url: '/lathe/plugins/hello-plugin/settings' });
}

describe('plugin settings page', () => {
  it('loads the form with its sections, help and saved values', async () => {
    const fetch = serve();
    renderSettings();

    expect((await screen.findByLabelText<HTMLInputElement>(/Greeting/)).value).toBe('Hi');
    expect(screen.getByRole('heading', { name: 'Hello Plugin settings' })).toBeTruthy();
    expect(screen.getByText('Appearance')).toBeTruthy();
    expect(screen.getByText('Shown at the top of the dashboard.')).toBeTruthy();
    expect(fetch.mock.calls[0]?.[0]).toBe('/lathe/api/plugins/hello-plugin/settings');
  });

  it('saves the typed values with a PUT and toasts', async () => {
    const fetch = serve(json(200, { saved: true }));
    renderSettings();

    fireEvent.change(await screen.findByLabelText(/Greeting/), { target: { value: 'Hello there' } });
    fireEvent.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => {
      expect(__panel.toasts.get().map((toast) => toast.message)).toContain('Settings saved.');
    });

    const [url, init] = fetch.mock.calls[1] ?? [];
    expect(url).toBe('/lathe/api/plugins/hello-plugin/settings');
    expect(init?.method).toBe('PUT');
    expect(JSON.parse(typeof init?.body === 'string' ? init.body : '{}')).toEqual({ greeting: 'Hello there' });
  });

  it('shows a 422 message under its field', async () => {
    serve(json(422, { message: 'The Greeting field is required.', errors: { greeting: ['The Greeting field is required.'] } }));
    renderSettings();

    fireEvent.change(await screen.findByLabelText(/Greeting/), { target: { value: '' } });
    fireEvent.click(screen.getByRole('button', { name: 'Save' }));

    expect(await screen.findByText('The Greeting field is required.')).toBeTruthy();
    expect(screen.getByLabelText(/Greeting/).getAttribute('aria-invalid')).toBe('true');
  });
});
