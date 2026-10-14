<?php

use Illuminate\Support\Facades\File;

function docsPage(string $path): string
{
    return File::get(dirname(__DIR__, 3)."/docs/{$path}");
}

it('pairs the tenancy page with both tenancy packages', function () {
    expect(docsPage('security/tenancy.md'))->toContain('`stancl/tenancy`', '`spatie/laravel-multitenancy`');
});

it('uses the Heartbeat plugin as the permission plugin example', function () {
    expect(docsPage('extending/permission-plugin.md'))->toContain('Heartbeat');
});

it('holds the default-limits table on the limits page', function () {
    $page = docsPage('getting-started/limits-and-performance.md');

    foreach (['limits.count_cap', 'pagination.type', 'pagination.per_page', 'limits.max_per_page', 'limits.picker_results', 'limits.search_term_chars', 'limits.filter_remote_threshold', 'limits.bulk_inline_max', 'limits.id_list_max', 'limits.metrics_ttl', 'global_search.results', 'saved_tabs.max_per_resource', 'uploads.max_file_size_mb', 'imports.max_file_size_mb', 'exports.chunk_size', 'imports.chunk_size'] as $key) {
        expect($page)->toContain("`{$key}`");
    }
});

it('keeps every secret out of the demo runbook', function () {
    $runbook = File::get(dirname(__DIR__, 3).'/scripts/demo/RUNBOOK.md');

    expect($runbook)->not->toMatch('/APP_KEY=base64:|DB_PASSWORD=\S|MYSQL_PWD=\S|(ghp|gho|github_pat|npm|sk)_[A-Za-z0-9]{12,}|BEGIN [A-Z ]*PRIVATE KEY/');
});

it('lets the quickstart policy save the belongsTo field it adds', function () {
    // A belongsTo field asks the policy for add{Relation}; the Author field reads the user relation.
    expect(docsPage('getting-started/quickstart.md'))->toContain("Field::belongsTo('Author', 'user'", 'public function addUser(');
});
