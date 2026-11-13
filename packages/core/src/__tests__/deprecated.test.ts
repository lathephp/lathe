/// <reference types="node" />
import { fileURLToPath } from 'node:url';
import ts from 'typescript';
import { expect, it } from 'vitest';

// What an editor shows a plugin author: the JSDoc tags of each export of the package's entry.
function deprecatedExports(): Map<string, string> {
  const entry = fileURLToPath(new URL('../index.ts', import.meta.url));
  const program = ts.createProgram([entry], { jsx: ts.JsxEmit.ReactJSX, module: ts.ModuleKind.ESNext, target: ts.ScriptTarget.ESNext, moduleResolution: ts.ModuleResolutionKind.Bundler });
  const checker = program.getTypeChecker();
  const source = program.getSourceFile(entry);
  const module = source && checker.getSymbolAtLocation(source);
  const deprecated = new Map<string, string>();

  for (const symbol of module ? checker.getExportsOfModule(module) : []) {
    const target = symbol.flags & ts.SymbolFlags.Alias ? checker.getAliasedSymbol(symbol) : symbol;
    const tag = target.getJsDocTags(checker).find((jsDoc) => jsDoc.name === 'deprecated');

    if (tag) {
      deprecated.set(symbol.name, ts.displayPartsToString(tag.text));
    }
  }

  return deprecated;
}

it('marks FilterMenu and filter() deprecated, saying the filter bar shows such a filter as a text key', () => {
  const deprecated = deprecatedExports();

  expect([...deprecated.keys()].sort()).toEqual(['FilterMenu', 'filter']);
  expect(deprecated.get('FilterMenu')).toContain('text key');
  expect(deprecated.get('filter')).toContain('text key');
}, 30_000);
