import { describe, expect, it } from 'vitest';

const palette =
  /\b(bg|text|border|ring|fill|stroke|outline|divide|decoration)-(red|green|blue|yellow|amber|emerald|sky|orange|lime|teal|cyan|indigo|violet|purple|fuchsia|pink|rose|slate|gray|zinc|neutral|stone|white|black)(-[0-9]{2,3})?(\/[0-9]+)?\b/;

// file:line entries that keep a palette colour on purpose, each with its reason.
const allowed: Record<string, string> = {
  // A QR code must stay dark on white to scan, in either theme.
  'pages/Security.tsx:159': 'QR code ground',
  // The label sits on bars filled with server-chosen or series colours, so no one token reads on all of them.
  'widgets/charts/RadialCard.tsx:102': 'label over series fills',
};

// The tests are left out.
const sources = import.meta.glob<string>(['../**/*.tsx', '!../__tests__/**'], { eager: true, query: '?raw', import: 'default' });

describe('panel colours', () => {
  it('use theme tokens, not palette classes', () => {
    const found = Object.entries(sources).flatMap(([file, source]) =>
      source.split('\n').flatMap((text, i) => (palette.test(text) ? [`${file.slice(3)}:${String(i + 1)}`] : [])),
    );

    expect(Object.keys(sources).length).toBeGreaterThan(50);
    expect(found.filter((entry) => !(entry in allowed))).toEqual([]);
  });
});
