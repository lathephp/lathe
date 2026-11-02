<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Runs open-issue.sh against a fake gh that creates issue 19. Each issue list call prints
 * the next of $lists, and the calls gh got come back one per line.
 *
 * @param  list<string>  $lists
 * @return list<string>
 */
function runOpenIssue(array $lists): array
{
    $dir = sys_get_temp_dir().'/open-issue-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists("$dir/bin");

    foreach ($lists as $i => $list) {
        File::put("$dir/list-$i", $list);
    }

    File::put("$dir/bin/gh", <<<'SH'
        #!/usr/bin/env bash
        echo "$*" >> "$FAKE/calls"
        case "$1 $2" in
            "issue list")
                n=$(cat "$FAKE/count" 2>/dev/null || echo 0)
                echo $((n + 1)) > "$FAKE/count"
                cat "$FAKE/list-$n"
                ;;
            "issue create") echo "https://github.com/lathephp/lathe/issues/19" ;;
        esac
        SH);
    chmod("$dir/bin/gh", 0755);

    Process::env(['PATH' => "$dir/bin:".getenv('PATH'), 'FAKE' => $dir])
        ->run(['bash', dirname(__DIR__, 3).'/scripts/ci/open-issue.sh', 'upstream', 'Title', 'Body'])
        ->throw();

    $calls = explode("\n", trim(File::get("$dir/calls")));
    File::deleteDirectory($dir);

    return array_values(array_filter($calls, fn (string $call) => ! str_starts_with($call, 'label') && ! str_starts_with($call, 'issue list')));
}

it('comments on the open issue when one exists', function () {
    expect(runOpenIssue(['7']))->toBe(['issue comment 7 --body Body']);
});

it('keeps the new issue when the list does not show it yet', function () {
    expect(runOpenIssue(['', '']))->toBe(['issue create --label upstream --title Title --body Body']);
});

it('keeps the new issue when another cell opened a newer one', function () {
    expect(runOpenIssue(['', '21']))->toBe(['issue create --label upstream --title Title --body Body']);
});

it('closes the new issue as a duplicate of an older one', function () {
    expect(runOpenIssue(['', '17']))->toBe([
        'issue create --label upstream --title Title --body Body',
        'issue close 19 --comment Duplicate of #17.',
        'issue comment 17 --body Body',
    ]);
});
