<?php

use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Yaml\Yaml;

/**
 * The three files of the Boost skill, keyed by file name.
 *
 * @return array<string, string>
 */
function skillFiles(): array
{
    $folder = dirname(__DIR__, 3).'/resources/boost/skills/lathe-development';
    $files = [];

    foreach (['SKILL.md', 'reference.md', 'custom-extensions.md'] as $name) {
        $files[$name] = is_file("{$folder}/{$name}") ? (string) file_get_contents("{$folder}/{$name}") : '';
    }

    return $files;
}

it('ships the three skill files', function () {
    foreach (skillFiles() as $name => $text) {
        expect($text)->not->toBe('', "{$name} is missing or empty");
    }
});

it('gives SKILL.md front matter with the skill name and a description', function () {
    preg_match('/\A---\R(.*?)\R---\R/s', skillFiles()['SKILL.md'], $match);
    $meta = Yaml::parse($match[1] ?? '');

    expect($meta)->toBeArray()
        ->and($meta['name'] ?? null)->toBe('lathe-development')
        ->and($meta['description'] ?? '')->toBeString()->not->toBe('');
});

it('names only lathe commands that exist', function () {
    $known = array_keys(Artisan::all());

    foreach (skillFiles() as $name => $text) {
        preg_match_all('/\blathe:[a-z][a-z-]*/', $text, $found);
        $unknown = array_values(array_diff(array_unique($found[0]), $known));

        expect($found[0])->not->toBeEmpty("{$name} names no lathe command")
            ->and($unknown)->toBe([], "{$name} names commands that do not exist: ".implode(', ', $unknown));
    }
});

it('writes php blocks that lint', function () {
    $checked = 0;

    foreach (skillFiles() as $name => $text) {
        preg_match_all('/^```php\R(.*?)^```/ms', $text, $blocks);

        foreach ($blocks[1] as $index => $block) {
            // A block that starts with <?php is linted as written. Any other block is
            // a fragment (a method, a property, a constant), so it is linted inside the body of a
            // class that has a parent, which lets a method call parent::boot().
            $source = str_starts_with(ltrim($block), '<?php') ? $block : "<?php\nclass SkillFragment extends SkillFragmentParent\n{\n{$block}\n}\n";

            $process = proc_open([PHP_BINARY, '-l'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            fwrite($pipes[0], $source);
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
            $status = proc_close($process);

            expect($status)->toBe(0, "{$name} php block ".($index + 1)." does not lint:\n{$output}\n{$block}");
            $checked++;
        }
    }

    expect($checked)->toBeGreaterThan(10);
});

it('names only Lathe classes that exist', function () {
    $named = [];

    foreach (skillFiles() as $text) {
        // Not preceded by a word character or a backslash, so App\Lathe\... is left alone.
        preg_match_all('/(?<![\w\\\\])Lathe(?:\\\\[A-Za-z_][A-Za-z0-9_]*)+/', $text, $found);
        $named = [...$named, ...$found[0]];
    }

    $named = array_values(array_unique($named));
    $missing = array_values(array_filter($named, fn (string $fqcn) => ! class_exists($fqcn) && ! interface_exists($fqcn) && ! trait_exists($fqcn) && ! enum_exists($fqcn)));

    expect($named)->not->toBeEmpty()
        ->and($missing)->toBe([], 'the skill names classes that do not exist: '.implode(', ', $missing));
});

it('passes the word check', function () {
    $root = dirname(__DIR__, 3);
    $output = (string) shell_exec('cd '.escapeshellarg($root).' && bash scripts/check-word.sh 2>&1');

    expect($output)->toContain('0 hits');
});
