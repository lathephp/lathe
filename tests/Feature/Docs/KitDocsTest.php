<?php

use Illuminate\Support\Facades\File;

/**
 * @return list<string> every markdown file the extension docs and the skill ship
 */
function kitDocFiles(): array
{
    $root = dirname(__DIR__, 3);
    $files = [];

    foreach (['docs/extending', 'resources/boost/skills/lathe-development'] as $folder) {
        foreach (File::allFiles($root.'/'.$folder) as $file) {
            if ($file->getExtension() === 'md') {
                $files[] = $folder.'/'.$file->getRelativePathname();
            }
        }
    }

    return $files;
}

function kitDoc(string $path): string
{
    return (string) file_get_contents(dirname(__DIR__, 3).'/'.$path);
}

it('teaches no hand-styled card, heading or four-second toast', function (string $needle) {
    $offenders = array_filter(kitDocFiles(), fn (string $path) => str_contains(kitDoc($path), $needle));

    expect(array_values($offenders))->toBe([]);
})->with([
    'a padded card' => 'className="p-6"',
    'a hand-styled title' => 'font-semibold leading-none tracking-tight',
    'a four-second toast' => 'four seconds',
]);

it('says a toast lasts five seconds', function (string $path) {
    expect(kitDoc($path))->toContain('five seconds');
})->with(['docs/extending/extension-api.md', 'docs/extending/notifications.md']);

it('has a Styling section in the frontend page', function () {
    $doc = kitDoc('docs/extending/frontend.md');

    expect($doc)->toContain("\n## Styling\n")
        ->and($doc)->toContain('@lathephp/core/tokens.css')
        ->and($doc)->toContain('@lathephp/vite-plugin/eslint');
});

it('marks every custom filter component as deprecated', function () {
    $offenders = [];

    foreach (kitDocFiles() as $path) {
        foreach (preg_split('/\n\s*\n/', kitDoc($path)) as $block) {
            foreach (explode("\n", $block) as $line) {
                $teachesCustomFilter = (str_contains($line, 'lathe:filter') && str_contains($line, '--custom'))
                    || str_contains($line, "filter('");

                if ($teachesCustomFilter && ! str_contains($block, 'eprecated')) {
                    $offenders[] = "{$path}: {$line}";
                }
            }
        }
    }

    expect($offenders)->toBe([]);
});
