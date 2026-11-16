<?php

// The Vitest setup preloads every lazy chunk in a beforeAll. Under load (a wave suite beside
// implementers running theirs) that took over the default 10 s hook timeout in 1 file, then in 38,
// while each file passed alone. Each beforeAll in the setup says how long it may take.

/**
 * The timeout, in ms, each beforeAll call in $source passes as its second argument; null when it passes none.
 *
 * @return list<int|null>
 */
function setupHookTimeouts(string $source): array
{
    $timeouts = [];
    $offset = 0;

    while (($start = strpos($source, 'beforeAll(', $offset)) !== false) {
        $depth = 0;
        $last = $start + strlen('beforeAll');

        for ($i = $last; $i < strlen($source); $i++) {
            if (in_array($source[$i], ['(', '[', '{'], true)) {
                $depth++;
            } elseif (in_array($source[$i], [')', ']', '}'], true)) {
                $depth--;

                if ($depth === 0) {
                    break;
                }
            } elseif ($depth === 1 && $source[$i] === ',') {
                $last = $i;
            }
        }

        $argument = $last > $start + strlen('beforeAll') ? trim(substr($source, $last + 1, $i - $last - 1)) : '';
        $timeouts[] = preg_match('/^[\d_]+$/', $argument) === 1 ? (int) str_replace('_', '', $argument) : null;
        $offset = $i;
    }

    return $timeouts;
}

it('gives every beforeAll of the Vitest setup a timeout of at least a minute', function () {
    $timeouts = setupHookTimeouts((string) file_get_contents(dirname(__DIR__, 3).'/resources/js/__tests__/setup.ts'));

    expect($timeouts)->not->toBe([]);

    foreach ($timeouts as $timeout) {
        expect($timeout)->not->toBeNull()->toBeGreaterThanOrEqual(60_000);
    }
});

it('reads a beforeAll with no timeout as null and one with a timeout as its number', function () {
    expect(setupHookTimeouts("beforeAll(async () => {\n  await Promise.all([a(), b()]);\n});"))->toBe([null])
        ->and(setupHookTimeouts("beforeAll(async () => {\n  await Promise.all([a(), b()]);\n}, 60_000);\nbeforeAll(() => f(1, 2), 5000);"))->toBe([60_000, 5000]);
});
