<?php

use Lathe\Tests\Octane\LaneSession;

// scripts/octane-lane.sh sets both URLs: the workbench under Octane with 2 FrankenPHP
// workers, and the same workbench under `testbench serve`, which runs every request in
// a fresh PHP process and so gives the golden responses.
$octaneUrl = getenv('LATHE_OCTANE_URL') ?: null;
$goldenUrl = getenv('LATHE_GOLDEN_URL') ?: null;

it('answers 600 requests over three contexts the way a fresh PHP process does', function () use ($octaneUrl, $goldenUrl) {
    $contexts = [
        'admin, UTC' => ['admin@lathephp.com', []],
        // PostPolicy denies the editor delete, and the Post Resource shows the editor only published posts.
        'editor, UTC' => ['editor@lathephp.com', []],
        // StripedTables stripes this one request; the workbench reads the user's timezone from the header.
        'admin, Pacific/Kiritimati, striped' => ['admin@lathephp.com', ['X-Bench-Timezone' => 'Pacific/Kiritimati', 'X-Bench-Striped' => '1']],
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

    $goldenProbe = $golden['admin, UTC']->get('/bench/probe');
    $names = array_keys($contexts);
    $memory = [];

    for ($i = 0; $i < 600; $i++) {
        $name = $names[$i % 3];
        $path = $paths[intdiv($i, 3) % 2];
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
    }

    // Each worker's highest memory over the 500 requests after the 100 warm-up ones,
    // against its highest during the warm-up.
    foreach ($memory as $pid => $samples) {
        $warm = array_column(array_filter($samples, fn (array $sample) => $sample[0] < 100), 1) ?: [$samples[0][1]];
        $after = array_column(array_filter($samples, fn (array $sample) => $sample[0] >= 100), 1);

        expect(max([0, ...$after]) - max($warm))->toBeLessThan(2 * 1024 * 1024, "worker {$pid} memory growth in bytes");
    }
})->group('octane')->skip($octaneUrl === null || $goldenUrl === null, 'needs LATHE_OCTANE_URL and LATHE_GOLDEN_URL; run scripts/octane-lane.sh');
