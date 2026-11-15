/// <reference types="node" />
import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { Button, buttonClass, type ButtonSize, type ButtonVariant } from '../components/ui/Button';

const variants: ButtonVariant[] = ['default', 'outline', 'secondary', 'ghost', 'destructive', 'destructive-solid', 'link'];
const sizes: ButtonSize[] = ['default', 'xs', 'sm', 'lg', 'icon', 'icon-xs', 'icon-sm', 'icon-lg'];

// The look comes from the active style's cn-button rules in the style stylesheet.
describe('the panel Button', () => {
  it('draws an outline small button with the style classes', () => {
    render(
      <Button variant="outline" size="sm">
        Filter
      </Button>,
    );

    expect([...screen.getByRole('button', { name: 'Filter' }).classList]).toEqual(expect.arrayContaining(['cn-button', 'cn-button-variant-outline', 'cn-button-size-sm']));
  });

  it.each(variants.flatMap((variant) => sizes.map((size) => [variant, size] as const)))('draws variant %s at size %s', (variant, size) => {
    render(
      <Button variant={variant} size={size}>
        Go
      </Button>,
    );

    expect(screen.getByRole('button', { name: 'Go' }).classList).toContain(`cn-button-variant-${variant}`);
    expect(screen.getByRole('button', { name: 'Go' }).classList).toContain(`cn-button-size-${size}`);
  });

  it('gives a link the same classes', () => {
    expect(buttonClass('ghost', 'sm').split(' ')).toEqual(expect.arrayContaining(['cn-button', 'cn-button-variant-ghost', 'cn-button-size-sm']));
  });

  it("lets a caller's className follow the style classes", () => {
    render(<Button className="w-full">Go</Button>);

    expect(screen.getByRole('button', { name: 'Go' }).className).toMatch(/cn-button-size-default w-full$/);
  });
});

// Built at run time, so this file does not find itself.
const oldClass = ['lathe', 'button', ''].join('-');
const oldInputNames = [['modal', 'Input'].join(''), ['field', 'Class'].join('')];

describe('the panel sources', () => {
  it('draw no button with the old lathe-button look classes', () => {
    const files = readdirSync('resources/js', { recursive: true, encoding: 'utf8' }).filter((file) => /\.tsx?$/.test(file) && !file.startsWith(join('components', 'ui')));

    expect(files.filter((file) => readFileSync(join('resources/js', file), 'utf8').includes(oldClass))).toEqual([]);
  });

  it('draw no input with the old hand-written input looks', () => {
    const files = readdirSync('resources/js', { recursive: true, encoding: 'utf8' }).filter((file) => /\.tsx?$/.test(file));

    expect(files.filter((file) => oldInputNames.some((name) => readFileSync(join('resources/js', file), 'utf8').includes(name)))).toEqual([]);
  });
});
