<?php

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

function repoRoot(): string
{
    return dirname(__DIR__, 3);
}

/**
 * @param  array<string, string>  $files  path under the fixture root => contents
 */
function plantTranslationRoot(array $files): string
{
    $root = sys_get_temp_dir().'/translations-'.bin2hex(random_bytes(6));

    foreach ($files as $path => $contents) {
        File::ensureDirectoryExists(dirname("$root/$path"));
        File::put("$root/$path", $contents);
    }

    return $root;
}

function runKeyCheck(string $root): ProcessResult
{
    return Process::path(repoRoot())->run(['node', 'scripts/check-translations.mjs', '--root', $root]);
}

/**
 * @return array<string, string>
 */
function cleanTranslationFiles(): array
{
    return [
        'lang/en.json' => '{"Save":"Save","Close":"Close"}',
        'lang/fr.json' => '{"Save":"Enregistrer","Close":"Fermer"}',
        'src/Thing.php' => "<?php\nLathe::__('Save');\n",
        'resources/js/a.tsx' => "export const a = () => __('Close');\n",
    ];
}

it('passes a fixture where every key is defined, used and translated', function () {
    $result = runKeyCheck(plantTranslationRoot(cleanTranslationFiles()));

    expect($result->exitCode())->toBe(0);
});

it('fails on a key a source file uses but en.json lacks', function () {
    $files = cleanTranslationFiles();
    $files['src/Thing.php'] = "<?php\nLathe::__('Save');\nLathe::__('Delete :name', ['name' => 'x']);\n";

    $result = runKeyCheck(plantTranslationRoot($files));

    expect($result->exitCode())->toBe(1)
        ->and($result->output().$result->errorOutput())->toContain('Delete :name');
});

it('fails on a key en.json holds that no source uses', function () {
    $files = cleanTranslationFiles();
    $files['lang/en.json'] = '{"Save":"Save","Close":"Close","Orphan":"Orphan"}';
    $files['lang/fr.json'] = '{"Save":"Enregistrer","Close":"Fermer","Orphan":"Orphelin"}';

    $result = runKeyCheck(plantTranslationRoot($files));

    expect($result->exitCode())->toBe(1)
        ->and($result->output().$result->errorOutput())->toContain('Orphan');
});

it('fails on a shipped locale that lacks a key en.json has', function () {
    $files = cleanTranslationFiles();
    $files['lang/fr.json'] = '{"Save":"Enregistrer"}';

    $result = runKeyCheck(plantTranslationRoot($files));

    expect($result->exitCode())->toBe(1)
        ->and($result->output().$result->errorOutput())->toContain('fr.json')
        ->and($result->output().$result->errorOutput())->toContain('Close');
});

it('passes on the real tree', function () {
    expect(runKeyCheck(repoRoot())->exitCode())->toBe(0);
});

function lintFixture(string $source): ProcessResult
{
    $rule = repoRoot().'/eslint-rules/no-untranslated-literal.js';
    $root = plantTranslationRoot([
        'eslint.config.js' => <<<JS
            import rule from '$rule';

            export default [
              {
                files: ['**/*.jsx'],
                languageOptions: { parserOptions: { ecmaFeatures: { jsx: true } } },
                plugins: { lathe: { rules: { 'no-untranslated-literal': rule } } },
                rules: { 'lathe/no-untranslated-literal': 'error' },
              },
            ];
            JS,
        'a.jsx' => $source,
    ]);

    return Process::path($root)->run([repoRoot().'/node_modules/.bin/eslint', '--max-warnings=0', 'a.jsx']);
}

it('fails the lint on a planted JSX text literal', function () {
    $result = lintFixture("export const A = () => <p>Hello there</p>;\n");

    expect($result->exitCode())->toBe(1)
        ->and($result->output())->toContain('Hello there');
});

it('fails the lint on a planted aria-label literal', function () {
    $result = lintFixture("export const A = () => <button aria-label=\"Close\" />;\n");

    expect($result->exitCode())->toBe(1)
        ->and($result->output())->toContain('aria-label');
});

it('fails the lint on title, placeholder, alt and label literals', function (string $prop) {
    $result = lintFixture("export const A = () => <input $prop=\"Words\" />;\n");

    expect($result->exitCode())->toBe(1);
})->with(['title', 'placeholder', 'alt', 'label']);

it('lets translated, punctuation-only and numeric text through', function () {
    $result = lintFixture(<<<'JSX'
        export const A = ({ __ }) => (
          <p title={__('Close')} aria-label={__('Close')}>
            {__('Hello')} · / — … × 12
          </p>
        );
        JSX);

    expect($result->exitCode())->toBe(0);
});
