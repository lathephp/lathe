import type { Plugin } from 'vite';

export interface PluginModeOptions {
  mode: 'plugin';
  /** The asset name the PHP plugin registers. The build writes dist/{name}.js. */
  name: string;
  /** The module that registers the plugin's components. */
  entry: string;
}

/** The plugin API range of @lathephp/core that a plugin built with this package needs. */
const coreRange = '^1.0';

// The panel's import map serves these, so a plugin runs on the panel's React and API.
const externals = [
  'react',
  'react/jsx-runtime',
  'react/compiler-runtime',
  'react-dom',
  'react-dom/client',
  '@lathephp/core',
  '@lathephp/ui',
];

// A registration call of @lathephp/core, whose literal first argument is the component key.
const registration = /(?<![\w$.])(field|filter|widget|plugin|page|resourcePlugin|actionModal)\(\s*(['"`])([^'"`]+)\2/g;

// A second copy of React breaks hooks, so a bundled React module fails the build.
const bundledReact = /[\\/]node_modules[\\/](react|react-dom)[\\/]/;

/**
 * Builds a plugin into one ES2022 module, dist/{name}.js, with no chunk and no hash in its name,
 * and writes dist/lathe-plugin.json listing the component keys the module registers.
 */
export function lathe(options: PluginModeOptions): Plugin {
  let components: Record<string, string[]> = {};

  return {
    name: 'lathe',
    enforce: 'pre',
    config() {
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
    },
    buildStart() {
      components = {};
    },
    transform(code, id) {
      if (id.includes('/node_modules/') || !code.includes('@lathephp/core')) {
        return null;
      }

      for (const [, kind = '', , key = ''] of code.matchAll(registration)) {
        const keys = (components[kind] ??= []);

        if (!keys.includes(key)) {
          keys.push(key);
        }
      }

      return null;
    },
    generateBundle(_, bundle) {
      const scripts: string[] = [];
      const styles: string[] = [];

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
      }

      this.emitFile({
        type: 'asset',
        fileName: 'lathe-plugin.json',
        source: `${JSON.stringify({ name: options.name, core: coreRange, scripts, styles, components }, null, 2)}\n`,
      });
    },
  };
}
