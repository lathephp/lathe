import { render } from '@testing-library/react';
import type { SafeHtml as SafeHtmlValue } from '@lathephp/core';
import { describe, expect, it } from 'vitest';
import { SafeHtml } from '../components/SafeHtml';

// On the wire the server sends { __html }, and only the type brands it.
const wire = { __html: '<p>Hello <strong>there</strong></p>' } as SafeHtmlValue;

describe('SafeHtml', () => {
  it('renders the html of a branded value', () => {
    const { container } = render(<SafeHtml html={wire} />);

    expect(container.innerHTML).toBe('<div><p>Hello <strong>there</strong></p></div>');
  });

  it('takes a class name', () => {
    const { container } = render(<SafeHtml html={wire} className="prose" />);

    expect(container.firstElementChild?.className).toBe('prose');
  });

  it('refuses a plain string and an unbranded object at compile time', () => {
    // Never called: the point is that tsc rejects both lines.
    const rejected = () => [
      // @ts-expect-error a plain string is not SafeHtml
      <SafeHtml html="<p>x</p>" />,
      // @ts-expect-error an object that was never branded is not SafeHtml
      <SafeHtml html={{ __html: '<p>x</p>' }} />,
    ];

    expect(rejected).toBeTypeOf('function');
  });
});
