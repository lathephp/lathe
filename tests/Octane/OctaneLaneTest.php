<?php

use Lathe\Tests\Octane\LaneSession;

// scripts/octane-lane.sh sets both URLs: the workbench under Octane with 2 FrankenPHP
// workers, and the same workbench under `testbench serve`, which runs every request in
// a fresh PHP process and so gives the golden responses.
$octaneUrl = getenv('LATHE_OCTANE_URL') ?: null;
$goldenUrl = getenv('LATHE_GOLDEN_URL') ?: null;

it('answers 600 requests over four contexts the way a fresh PHP process does', function () use ($octaneUrl, $goldenUrl) {
    $contexts = [
        'admin, UTC' => ['admin@lathephp.com', []],
        // PostPolicy denies the editor delete, and the Post Resource shows the editor only published posts.
        'editor, UTC' => ['editor@lathephp.com', []],
        // StripedTables stripes this one request; the workbench reads the user's timezone from the header.
        'admin, Pacific/Kiritimati, striped' => ['admin@lathephp.com', ['X-Bench-Timezone' => 'Pacific/Kiritimati', 'X-Bench-Striped' => '1']],
        // ArabicContext serves this one request in Arabic, right to left.
        'admin, ar' => ['admin@lathephp.com', ['X-Bench-Locale' => 'ar']],
    ];
    $paths = ['/lathe/resources/orders', '/lathe/resources/posts'];

    $golden = $octane = $goldens = [];

    foreach ($contexts as $name => [$email, $headers]) {
        $golden[$name] = new LaneSession((string) $goldenUrl, $email);
        $octane[$name] = new LaneSession((string) $octaneUrl, $email);

        foreach ($paths as $path) {
            $goldens[$name][$path] = $golden[$name]->get($path, $headers);
        }
    }

    // The fresh process stripes the third context, so a worker that drops the change shows as a difference.
    expect($goldens['admin, Pacific/Kiritimati, striped'][$paths[0]]['body']['props']['tableStyle']['striped'])->toBeTrue();

    // The fresh process answers the ar context in Arabic, right to left, and the admin context in English.
    expect($goldens['admin, ar'][$paths[0]]['body']['props'])->toMatchArray(['locale' => 'ar', 'rtl' => true])
        ->and($goldens['admin, UTC'][$paths[0]]['body']['props'])->toMatchArray(['locale' => 'en', 'rtl' => false]);

    $goldenProbe = $golden['admin, UTC']->get('/bench/probe');
    $names = array_keys($contexts);
    $memory = [];

    for ($i = 0; $i < 600; $i++) {
        $name = $names[$i % 4];
        $path = $paths[intdiv($i, 4) % 2];
        $response = $octane[$name]->get($path, $contexts[$name][1]);

        expect($response['status'])->toBe($goldens[$name][$path]['status'], "request {$i}, {$name}, {$path}: status")
            ->and(LaneSession::firstDifference($goldens[$name][$path]['body'], $response['body']))->toBeNull("request {$i}, {$name}, {$path}")
            ->and($response['memory'])->toBeInt("request {$i} has no X-Worker-Memory header");

        $memory[$response['pid']][] = [$i, $response['memory']];

        if ($name === $names[2]) {
            // The admin's next request sees none of the Kiritimati context: default config and timezone, the whole Post scope.
            expect($octane['admin, UTC']->get('/bench/probe')['body'])->toBe([
                'striped' => false,
                'fortify' => $goldenProbe['body']['fortify'],
                'timezone' => 'UTC',
                'scope' => 190,
            ], "probe after request {$i}");
        }

        if ($name === $names[3] && $i % 100 === 3) {
            // The page root reads lang and dir for the ar request, and is back to en and ltr for the admin's next one.
            expect($octane[$name]->html($paths[0], $contexts[$name][1]))->toContain('<html lang="ar" dir="rtl">')
                ->and($octane['admin, UTC']->html($paths[0]))->toContain('<html lang="en" dir="ltr">');
        }
    }

    // Each worker's highest memory over the 500 requests after the 100 warm-up ones,
    // against its highest during the warm-up.
    foreach ($memory as $pid => $samples) {
        $warm = array_column(array_filter($samples, fn (array $sample) => $sample[0] < 100), 1) ?: [$samples[0][1]];
        $after = array_column(array_filter($samples, fn (array $sample) => $sample[0] >= 100), 1);

        expect(max([0, ...$after]) - max($warm))->toBeLessThan(2 * 1024 * 1024, "worker {$pid} memory growth in bytes");
    }
})->group('octane')->skip($octaneUrl === null || $goldenUrl === null, 'needs LATHE_OCTANE_URL and LATHE_GOLDEN_URL; run scripts/octane-lane.sh');
