import { fireEvent, render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { DetailField, FormField, IndexField } from '../fields/registry';
import type { FieldSchema } from '../fields/types';

function schema(component: string, overrides: Partial<FieldSchema> = {}): FieldSchema {
  return {
    key: 'photo',
    attribute: 'photo',
    label: 'Photo',
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
    ...overrides,
  };
}

function display(value: unknown): string {
  return value === null ? '' : typeof value === 'string' ? value : JSON.stringify(value);
}

function renderIndex(field: FieldSchema, value: unknown, shown = display(value)) {
  return render(<IndexField field={field} cell={{ value, display: shown }} />).container;
}

function renderDetail(field: FieldSchema, value: unknown, shown = display(value), extra: Record<string, unknown> = {}) {
  return render(<DetailField field={{ ...field, value, display: shown, ...extra }} />).container;
}

function renderForm(field: FieldSchema, value: unknown, onChange: (value: unknown) => void = () => undefined) {
  return render(
    <>
      <label htmlFor="photo">Photo</label>
      <FormField field={{ ...field, value }} value={value} onChange={onChange} id="photo" describedBy={undefined} invalid={false} disabled={false} />
    </>,
  ).container;
}

const file = { path: 'photos/a.png', name: 'a.png', size: 120, preview: '/storage/photos/a.png', thumbnail: '/storage/photos/a-thumb.png', downloadable: true };

describe('file-field', () => {
  const field = schema('file-field');

  it('shows the four values on index, detail and form', async () => {
    expect(renderIndex(field, 5).textContent).toBe('5');
    expect(renderIndex(field, 'x').textContent).toBe('x');
    expect(renderIndex(field, true, 'Yes').textContent).toBe('Yes');
    expect(renderIndex(field, null).textContent).toBe('');
    expect(renderDetail(field, 5).textContent).toBe('5');
    expect(renderDetail(field, null, '').textContent).toBe('—');
    // The file control is a lazy chunk, so the form shows it once it arrives.
    expect((await within(renderForm(field, 'x')).findByText('No file')).textContent).toBe('No file');
    expect((await within(renderForm(field, null)).findByText('No file')).textContent).toBe('No file');
  });

  it('shows the file name on the index', () => {
    expect(renderIndex(field, file, 'a.png').textContent).toBe('a.png');
  });

  it('links the name to the download on the detail page when the file may be downloaded', () => {
    const container = renderDetail(field, file, 'a.png', { download: '/lathe/api/resources/docs/3/download/photo' });

    expect(container.querySelector('a')?.getAttribute('href')).toBe('/lathe/api/resources/docs/3/download/photo');
    expect(container.textContent).toBe('a.png');
  });

  it('shows only the name when downloads are off', () => {
    const container = renderDetail(field, { ...file, downloadable: false }, 'a.png', { download: '/x' });

    expect(container.querySelector('a')).toBeNull();
    expect(container.textContent).toBe('a.png');
  });

  it('shows the current file with a remove control that sends null', () => {
    const onChange = vi.fn();
    renderForm(field, file, onChange);

    expect(screen.getByText('a.png')).toBeTruthy();
    fireEvent.click(screen.getByRole('button', { name: 'Remove' }));
    expect(onChange).toHaveBeenCalledWith(null);
  });

  it('leaves out the remove control when the file is not deletable', () => {
    renderForm(schema('file-field', { deletable: false }), file);

    expect(screen.queryByRole('button', { name: 'Remove' })).toBeNull();
  });

  it('keeps a slot for the uploader', () => {
    expect(renderForm(field, null).querySelector('[data-upload-slot]')).not.toBeNull();
  });
});

describe('image-field', () => {
  const field = schema('image-field', { indexWidth: 32, detailWidth: 128, rounded: true });

  it('shows the four values on index, detail and form', () => {
    expect(renderIndex(field, 5).textContent).toBe('5');
    expect(renderIndex(field, 'x').textContent).toBe('x');
    expect(renderIndex(field, true, 'Yes').textContent).toBe('Yes');
    expect(renderIndex(field, null).querySelector('[data-placeholder]')).not.toBeNull();
    expect(renderDetail(field, 5).textContent).toBe('5');
    expect(renderDetail(field, null, '').textContent).toBe('—');
    expect(renderForm(field, 'x').textContent).toContain('No file');
    expect(renderForm(field, null).textContent).toContain('No file');
  });

  it('draws a rounded 32 px thumbnail on the index', () => {
    const image = renderIndex(field, file, 'a.png').querySelector('img');

    expect(image?.getAttribute('src')).toBe('/storage/photos/a-thumb.png');
    expect(image?.style.width).toBe('32px');
    expect(image?.className).toContain('rounded-full');
  });

  it('draws a 128 px image on the detail page with no download link', () => {
    const container = renderDetail(field, file, 'a.png', { download: '/x' });

    expect(container.querySelector('img')?.getAttribute('src')).toBe('/storage/photos/a.png');
    expect(container.querySelector('img')?.style.width).toBe('128px');
    expect(container.querySelector('a')).toBeNull();
  });

  it('squares the corners and sets the aspect ratio', () => {
    const image = renderIndex(schema('image-field', { squared: true, aspect: '16/9' }), file, 'a.png').querySelector('img');

    expect(image?.className).toContain('rounded-none');
    expect(image?.style.aspectRatio).toBe('16/9');
  });

  it('broken_image_shows_the_placeholder', () => {
    const container = renderIndex(field, file, 'a.png');

    fireEvent.error(container.querySelector('img') as HTMLImageElement);

    expect(container.querySelector('img')).toBeNull();
    expect(container.querySelector('[data-placeholder]')).not.toBeNull();
  });

  it('shows the current image on the form with a remove control', () => {
    const onChange = vi.fn();
    const container = renderForm(field, file, onChange);

    expect(container.querySelector('img')?.getAttribute('src')).toBe('/storage/photos/a.png');
    fireEvent.click(screen.getByRole('button', { name: 'Remove' }));
    expect(onChange).toHaveBeenCalledWith(null);
  });
});

describe('audio-field', () => {
  const field = schema('audio-field', { preload: 'metadata' });
  const sound = { ...file, path: 'sounds/a.mp3', name: 'a.mp3', preview: '/storage/sounds/a.mp3' };

  it('shows the four values on index, detail and form', () => {
    expect(renderIndex(field, 5).textContent).toBe('5');
    expect(renderIndex(field, 'x').textContent).toBe('x');
    expect(renderIndex(field, null).textContent).toBe('');
    expect(renderDetail(field, true, 'Yes').textContent).toBe('Yes');
    expect(renderDetail(field, null, '').textContent).toBe('—');
    expect(renderForm(field, 'x').textContent).toContain('No file');
    expect(renderForm(field, null).textContent).toContain('No file');
  });

  it('plays the file inline on the detail page and the form with the preload strategy', () => {
    const detail = renderDetail(field, sound, 'a.mp3').querySelector('audio');
    const form = renderForm(field, sound).querySelector('audio');

    expect([detail?.getAttribute('src'), detail?.getAttribute('preload'), detail?.hasAttribute('controls')]).toEqual(['/storage/sounds/a.mp3', 'metadata', true]);
    expect(form?.getAttribute('src')).toBe('/storage/sounds/a.mp3');
  });

  it('falls back to auto for an unknown preload value', () => {
    expect(renderDetail(schema('audio-field', { preload: 'eager' }), sound, 'a.mp3').querySelector('audio')?.getAttribute('preload')).toBe('auto');
  });
});

describe('ui-avatar-field', () => {
  const field = schema('ui-avatar-field', { indexWidth: 32, detailWidth: 128 });
  const initials = { url: null, initials: 'AU', style: { fontSize: 0.5, color: '7F9CF5', background: 'EBF4FF', bold: false }, rounded: true };

  it('shows the four values on index and detail', () => {
    expect(renderIndex(field, 5).textContent).toBe('5');
    expect(renderIndex(field, 'x').textContent).toBe('x');
    expect(renderIndex(field, true, 'Yes').textContent).toBe('Yes');
    expect(renderIndex(field, null).textContent).toBe('');
    expect(renderDetail(field, 5).textContent).toBe('5');
    expect(renderDetail(field, null, '').textContent).toBe('—');
  });

  it('draws round initials as an SVG with the configured colors and no image request', () => {
    const container = renderIndex(schema('ui-avatar-field', { indexWidth: 32 }), { ...initials, style: { fontSize: 0.8, color: '112233', background: 'ABCDEF', bold: true } });
    const svg = container.querySelector('svg');

    expect(svg?.getAttribute('width')).toBe('32');
    expect(svg?.querySelector('circle')?.getAttribute('fill')).toBe('#ABCDEF');
    expect(svg?.querySelector('text')?.getAttribute('fill')).toBe('#112233');
    expect(svg?.querySelector('text')?.getAttribute('font-weight')).toBe('700');
    expect(svg?.querySelector('text')?.getAttribute('font-size')).toBe('80');
    expect(svg?.textContent).toBe('AU');
    expect(container.querySelector('img')).toBeNull();
  });

  it('draws 128 px initials on the detail page', () => {
    expect(renderDetail(field, initials).querySelector('svg')?.getAttribute('width')).toBe('128');
  });

  it('ignores a style that is not hex', () => {
    const svg = renderIndex(field, { ...initials, style: { color: 'red;}', background: 'url(x)' } }).querySelector('svg');

    expect(svg?.querySelector('circle')?.getAttribute('fill')).toBe('#EBF4FF');
    expect(svg?.querySelector('text')?.getAttribute('fill')).toBe('#7F9CF5');
  });

  it('draws a round image from a url and falls back to the initials when it fails', () => {
    const container = renderIndex(field, { ...initials, url: 'https://www.gravatar.com/avatar/abc?s=300' });
    const image = container.querySelector('img');

    expect(image?.getAttribute('src')).toBe('https://www.gravatar.com/avatar/abc?s=300');
    expect(image?.className).toContain('rounded-full');

    fireEvent.error(image as HTMLImageElement);
    expect(container.querySelector('img')).toBeNull();
    expect(container.querySelector('svg')?.textContent).toBe('AU');
  });
});
