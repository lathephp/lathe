import { transformSync } from '@babel/core';
import { expect, it } from 'vitest';

const greeting = `
export function Greeting({ name }: { name: string }) {
  DIRECTIVE
  return <p className="greeting">Hello {name}</p>;
}
`;

function compile(directive: string): string {
  const result = transformSync(greeting.replace('DIRECTIVE', directive), {
    filename: 'Greeting.tsx',
    babelrc: false,
    configFile: false,
    parserOpts: { plugins: ['jsx', 'typescript'] },
    plugins: ['babel-plugin-react-compiler'],
  });

  return result?.code ?? '';
}

it('gives a component the memo cache', () => {
  const code = compile('');

  expect(code).toContain('import { c as _c } from "react/compiler-runtime";');
  expect(code).toContain('const $ = _c(2);');
});

it('leaves a component marked "use no memo" alone', () => {
  const code = compile('"use no memo";');

  expect(code).toContain('return <p className="greeting">Hello {name}</p>;');
  expect(code).not.toContain('react/compiler-runtime');
});
