<?php

use Lathe\Enums\Icon;
use Symfony\Component\Process\Process;

it('has 156 cases, each an icon of the bundled Lucide set', function () {
    $process = new Process(['bash', 'scripts/pnpm.sh', 'exec', 'node', 'scripts/lucide-names.mjs'], dirname(__DIR__, 3), timeout: 60);
    $process->mustRun();
    $lucide = array_flip(array_filter(explode("\n", $process->getOutput())));

    $missing = array_values(array_filter(Icon::cases(), fn (Icon $icon) => ! isset($lucide[$icon->name])));

    expect(Icon::cases())->toHaveCount(156)
        ->and(array_map(fn (Icon $icon) => $icon->name, $missing))->toBe([]);
})->group('frontend');
