// Prints the public exports of @lathephp/core and @lathephp/ui, one per line, sorted:
// "<package> <name>" for values and "<package> type <name>" for types; @internal ones are left out.
// Usage: node scripts/js-api.mjs [repo-root]
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { join, resolve } from 'node:path';

const root = resolve(process.argv[2] ?? '.');
const require = createRequire(import.meta.url);
const ts = require('typescript');

const lines = [];

for (const folder of ['core', 'ui']) {
  const dir = join(root, 'packages', folder);
  const name = JSON.parse(readFileSync(join(dir, 'package.json'), 'utf8')).name;
  const entry = join(dir, 'src', 'index.ts');
  const program = ts.createProgram([entry], {
    jsx: ts.JsxEmit.ReactJSX,
    module: ts.ModuleKind.ESNext,
    moduleResolution: ts.ModuleResolutionKind.Bundler,
    target: ts.ScriptTarget.ES2022,
    skipLibCheck: true,
    noEmit: true,
  });
  const checker = program.getTypeChecker();
  const source = program.getSourceFile(entry);
  const module = source && checker.getSymbolAtLocation(source);

  for (const symbol of module ? checker.getExportsOfModule(module) : []) {
    const target = symbol.flags & ts.SymbolFlags.Alias ? checker.getAliasedSymbol(symbol) : symbol;

    // Exports tagged @internal are the panel's own wiring, not public API.
    if (target.getJsDocTags(checker).some((tag) => tag.name === 'internal')) {
      continue;
    }

    const isValue = (target.flags & ts.SymbolFlags.Value) !== 0;
    lines.push(isValue ? `${name} ${symbol.name}` : `${name} type ${symbol.name}`);
  }
}

process.stdout.write(lines.sort().join('\n') + '\n');
