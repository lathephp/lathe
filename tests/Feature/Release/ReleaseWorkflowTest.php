<?php

use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

function releaseWorkflow(): array
{
    return Yaml::parseFile(dirname(__DIR__, 3).'/.github/workflows/release.yml');
}

function releaseRuns(string $job): string
{
    return collect(releaseWorkflow()['jobs'][$job]['steps'] ?? [])->pluck('run')->filter()->implode("\n");
}

it('runs on a v tag and by hand with a dry run that defaults on', function () {
    $on = releaseWorkflow()['on'];

    expect($on['push']['tags'])->toBe(['v*'])
        ->and($on['workflow_dispatch']['inputs']['dry_run'])->toMatchArray(['type' => 'boolean', 'default' => true]);
});

it('starts with the word check, then the required jobs, then the smoke install', function () {
    $jobs = releaseWorkflow()['jobs'];

    expect(array_key_first($jobs))->toBe('word-check')
        ->and(releaseRuns('word-check'))->toBe('bash scripts/check-word.sh')
        ->and($jobs['required'])->toMatchArray(['needs' => 'word-check', 'uses' => './.github/workflows/ci.yml'])
        ->and($jobs['smoke']['needs'])->toBe('required')
        ->and(releaseRuns('smoke'))->toContain('bash scripts/release-smoke.sh');
});

it('skips every publishing job on a dry run and off a tag', function (string $job) {
    expect(releaseWorkflow()['jobs'][$job]['if'])->toBe("startsWith(github.ref, 'refs/tags/v') && inputs.dry_run != true");
})->with(['publish-npm', 'github-release', 'dispatch']);

it('publishes the three npm packages with provenance under beta for a prerelease', function () {
    $job = releaseWorkflow()['jobs']['publish-npm'];
    $runs = releaseRuns('publish-npm');

    expect($job['permissions'])->toMatchArray(['id-token' => 'write'])
        ->and($runs)->toContain('npm publish --provenance --access public --tag "$dist_tag"', 'for package in core ui vite-plugin', 'dist_tag=beta', 'dist_tag=latest');
});

it('creates the GitHub release from the changelog and dispatches the site build', function () {
    expect(releaseRuns('github-release'))->toContain('CHANGELOG.md', 'gh release create')
        ->and(releaseRuns('dispatch'))->toContain('repos/lathephp/lathephp.com/dispatches')
        ->not->toContain('repos/lathephp/benchmarks');
});

it('holds no npm token, no token of the old release and no upload to another host', function () {
    foreach (File::glob(dirname(__DIR__, 3).'/.github/workflows/*.yml') as $workflow) {
        // Built from pieces so this file never holds the old product's name.
        expect(File::get($workflow))->not->toContain('NPM_TOKEN')
            ->not->toContain('NODE_AUTH_TOKEN')
            ->not->toContain('LA'.'XA_RELEASE_TOKEN')
            ->not->toMatch('/curl[^\n]*(-F|--upload-file|-T)\b/');
    }
});

it('grants the called CI workflow the permissions its jobs declare', function () {
    expect(releaseWorkflow()['jobs']['required']['permissions'] ?? null)->toBe(['contents' => 'read', 'issues' => 'write']);
});
