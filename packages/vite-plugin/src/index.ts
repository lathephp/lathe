import { existsSync, mkdirSync, readdirSync, readFileSync, realpathSync, rmSync, writeFileSync } from 'node:fs';
import type { AddressInfo } from 'node:net';
import { join, relative, resolve, sep } from 'node:path';
import { build, type Plugin } from 'vite';

export interface PluginModeOptions {
  mode: 'plugin';
  /** The asset name the PHP plugin registers. The build writes dist/{name}.js. */
  name: string;
  /** The module that registers the plugin's components. */
  entry: string;
}

export interface AppModeOptions {
  mode: 'app';
  /** The module that registers the app's components. Defaults to resources/js/lathe/boot.ts. */
  bootFile?: string;
  /**
   * Build each path-repository plugin that Composer's installed.json or lathe-components lists
   * with extra.lathe.entry to its dist/, and rebuild it on change in dev. Defaults to true.
   */
  autoDiscover?: boolean;
}

/** The plugin API range of @lathephp/core that a plugin built with this package needs. */
const coreRange = '^1.0';

/** Where the app build writes its modules, manifest.json and, in dev, the hot file. */
const extensionsDirectory = 'public/vendor/lathe/extensions';

// The panel's import map serves these, so a plugin runs on the panel's React and API.
const externals = [
  'react',
  'react/jsx-runtime',
  'react/compiler-runtime',
  'react-dom',
  'react-dom/client',
  '@lathephp/core',
  '@lathephp/ui',
  /^@lathephp\/ui\//,
];

// A registration call of @lathephp/core, whose literal first argument is the component key.
const registration = /(?<![\w$.])(field|filter|widget|plugin|page|resourcePlugin|actionModal)\(\s*(['"`])([^'"`]+)\2/g;

// A second copy of React breaks hooks, so a bundled React module fails the build.
const bundledReact = /[\\/]node_modules[\\/](react|react-dom)[\\/]/;

// The panel's stylesheet already holds the preflight and the token values, so the full
// Tailwind import becomes its theme and utilities layers only.
const fullTailwind = /@import\s+(['"])tailwindcss\1([^;]*);/;

// An import the dev server rewrote to its /@id/ URL.
const wrappedImport = /(["'])\/@id\/([^"']+)\1/g;

interface ComposerPackage {
  dist?: { type?: string };
  'install-path'?: string;
  extra?: { lathe?: { entry?: string } };
}

function isExternal(id: string): boolean {
  return externals.some((external) => (typeof external === 'string' ? external === id : external.test(id)));
}

function readJson(file: string): unknown {
  return existsSync(file) ? (JSON.parse(readFileSync(file, 'utf8')) as unknown) : null;
}

/**
 * The folders of the path-repository plugins that name an entry: the path packages in
 * vendor/composer/installed.json and the packages under lathe-components.
 */
function discoveredPlugins(root: string): string[] {
  const folders = new Set<string>();
  const installed = readJson(join(root, 'vendor/composer/installed.json'));
  const packages = (Array.isArray(installed) ? installed : ((installed as { packages?: unknown } | null)?.packages ?? [])) as ComposerPackage[];

  for (const pkg of packages) {
    const path = pkg['install-path'];

    if (pkg.extra?.lathe?.entry !== undefined && pkg.dist?.type === 'path' && path !== undefined) {
      folders.add(realpathSync(resolve(root, 'vendor/composer', path)));
    }
  }

  const components = join(root, 'lathe-components');

  for (const name of existsSync(components) ? readdirSync(components) : []) {
    const pkg = readJson(join(components, name, 'composer.json')) as ComposerPackage | null;

    if (pkg?.extra?.lathe?.entry !== undefined) {
      folders.add(realpathSync(join(components, name)));
    }
  }

  return [...folders];
}

/**
 * Builds a plugin into one ES2022 module, dist/{name}.js, with no chunk and no hash in its name,
 * and writes dist/lathe-plugin.json listing the component keys the module registers.
 *
 * In app mode, builds the boot file into hashed ES modules and a manifest.json in
 * public/vendor/lathe/extensions, the folder Lathe loads the app's extensions from.
 */
export function lathe(options: PluginModeOptions | AppModeOptions): Plugin {
  const bootFile = options.mode === 'app' ? (options.bootFile ?? 'resources/js/lathe/boot.ts') : '';
  let components: Record<string, string[]> = {};
  let root = process.cwd();
  let mode = 'production';
  let command = 'build';

  async function buildPlugins(watch: boolean) {
    if (options.mode !== 'app' || options.autoDiscover === false) {
      return [];
    }

    const watchers = [];

    for (const folder of discoveredPlugins(root)) {
      const result = await build({ root: folder, mode, build: watch ? { watch: {} } : {} });

      if ('close' in result) {
        watchers.push(result);
      }
    }

    return watchers;
  }

  return {
    name: 'lathe',
    enforce: 'pre',
    config(config) {
      if (options.mode === 'plugin') {
        return {
          build: {
            target: 'es2022',
            lib: { entry: options.entry, formats: ['es'], fileName: () => `${options.name}.js`, cssFileName: options.name },
            rolldownOptions: {
              external: externals,
              output: { codeSplitting: false },
            },
          },
        };
      }

      return {
        // Relative chunk imports, so the modules load from wherever the folder is served.
        base: './',
        // The panel's import map serves the externals, so the dev server bundles none of them.
        optimizeDeps: { noDiscovery: true, include: [] },
        build: {
          target: 'es2022',
          outDir: resolve(config.root ?? '', extensionsDirectory),
          emptyOutDir: true,
          modulePreload: false,
          rolldownOptions: {
            input: bootFile,
            external: externals,
            output: { entryFileNames: '[name]-[hash].js', chunkFileNames: '[name]-[hash].js', assetFileNames: '[name]-[hash][extname]' },
          },
        },
      };
    },
    configResolved(config) {
      root = config.root;
      mode = config.mode;
      command = config.command;
    },
    resolveId(id) {
      return isExternal(id) ? { id, external: true } : null;
    },
    async buildStart() {
      components = {};

      if (command === 'build') {
        await buildPlugins(false);
      }
    },
    configureServer(server) {
      if (options.mode === 'plugin') {
        return;
      }

      const hot = join(root, extensionsDirectory, 'hot');
      const clean = () => {
        rmSync(hot, { force: true });
      };

      // The dev server serves a bare import as /@id/{specifier}. The page's import map serves the
      // externals, so their imports go back to the bare name and share the panel's modules.
      server.middlewares.use((_request, response, next) => {
        const end = response.end.bind(response);

        response.end = ((chunk: unknown, ...rest: never[]) => {
          if ((typeof chunk !== 'string' && !Buffer.isBuffer(chunk)) || !/javascript/.test(String(response.getHeader('Content-Type')))) {
            return end(chunk, ...rest);
          }

          const code = chunk.toString().replace(wrappedImport, (match, quote: string, id: string) => (isExternal(id) ? `${quote}${id}${quote}` : match));
          response.setHeader('Content-Length', Buffer.byteLength(code));

          return end(code, ...rest);
        }) as typeof response.end;

        next();
      });

      server.httpServer?.once('listening', () => {
        const address = server.httpServer?.address() as AddressInfo;
        const host = typeof server.config.server.host === 'string' ? server.config.server.host : 'localhost';
        const protocol = server.config.server.https === undefined ? 'http' : 'https';

        mkdirSync(join(root, extensionsDirectory), { recursive: true });
        writeFileSync(hot, `${protocol}://${host}:${String(address.port)}/${relative(root, resolve(root, bootFile)).split(sep).join('/')}`);
      });
      server.httpServer?.once('close', clean);
      process.once('exit', clean);

      // A stopped dev server must not leave the hot file behind, or the page keeps asking it.
      for (const signal of ['SIGINT', 'SIGTERM', 'SIGHUP'] as const) {
        process.once(signal, () => process.exit());
      }

      void buildPlugins(true).then((watchers) => {
        server.httpServer?.once('close', () => {
          watchers.forEach((watcher) => void watcher.close());
        });
      });
    },
    // Components render with the panel's React, so a change reloads the page instead of refreshing.
    handleHotUpdate({ server }) {
      if (options.mode === 'plugin') {
        return;
      }

      server.ws.send({ type: 'full-reload' });

      return [];
    },
    transform(code, id) {
      if (id.endsWith('.css')) {
        return fullTailwind.test(code)
          ? code.replace(fullTailwind, '@import "tailwindcss/theme.css" layer(theme);\n@import "tailwindcss/utilities.css" layer(utilities)$2;')
          : null;
      }

      if (!id.includes('/node_modules/') && code.includes('@lathephp/core')) {
        for (const [, kind = '', , key = ''] of code.matchAll(registration)) {
          const keys = (components[kind] ??= []);

          if (!keys.includes(key)) {
            keys.push(key);
          }
        }
      }

      // The page loads the boot module from the dev server, so it brings the client that reloads it.
      return command === 'serve' && resolve(root, bootFile) === id ? `import '/@vite/client';\n${code}` : null;
    },
    // After Vite's CSS plugin, so the bundle holds the stylesheet.
    generateBundle: {
      order: 'post',
      handler(_, bundle) {
        const scripts: string[] = [];
        const styles: string[] = [];
        let entry = '';

        for (const file of Object.values(bundle)) {
          if (file.type === 'asset') {
            if (file.fileName.endsWith('.css')) {
              styles.push(file.fileName);
            }

            continue;
          }

          for (const id of Object.keys(file.modules)) {
            const match = bundledReact.exec(id);

            if (match !== null) {
              this.error(
                `The plugin build bundles ${match[1] ?? 'react'} code from ${id}. Import it by its bare name, so the panel's copy is used.`,
              );
            }
          }

          scripts.push(file.fileName);

          if (file.isEntry) {
            entry = file.fileName;
          }
        }

        this.emitFile(
          options.mode === 'plugin'
            ? {
                type: 'asset',
                fileName: 'lathe-plugin.json',
                source: `${JSON.stringify({ name: options.name, core: coreRange, scripts, styles, components }, null, 2)}\n`,
              }
            : { type: 'asset', fileName: 'manifest.json', source: `${JSON.stringify({ entry, components }, null, 2)}\n` },
        );
      },
    },
  };
}
