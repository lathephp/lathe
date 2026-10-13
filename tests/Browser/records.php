<?php

/*
 * Helpers for the record page tests: sign in and open a page, serve the public disk to the
 * browser, build the upload fixtures and record native dialogs.
 */

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Pest\Browser\Api\AwaitableWebpage;

/**
 * Sign in through the form and open a panel URL.
 */
function signedInAt(string $url, string $project = 'desktop-chrome', string $email = 'admin@lathephp.com'): AwaitableWebpage
{
    forgetServerSession();
    $page = latheVisit('/lathe/login', $project);
    signInThroughForm($page, $email);
    $page->assertPathIs('/lathe');

    return $page->navigate($url);
}

/**
 * Forget the signed-in user the in-process server holds. The server keeps one session store
 * and one guard between requests: a request without a session cookie would read the last
 * request's attributes and user, so a second browser context would start signed in.
 */
function forgetServerSession(): void
{
    app('session')->driver()->flush();
    Auth::forgetGuards();
}

/**
 * Point the public disk at public/storage for this test, so the in-process server hands the
 * browser the stored files the way a host app's storage link does.
 */
function servePublicDisk(): void
{
    $root = public_path('storage');
    File::ensureDirectoryExists($root);
    config([
        'filesystems.disks.public.root' => $root,
        'filesystems.disks.public.url' => '/storage',
    ]);

    test()->beforeApplicationDestroyed(fn () => File::deleteDirectory($root));
}

/**
 * A file the upload tests choose, built once in the temp folder so no binary sits in git:
 * avatar-3mb.png is a real 1024 x 1024 PNG of noise (about 3 MB), file-12mb.bin 12 MB of zeros.
 */
function uploadFixture(string $name): string
{
    $directory = sys_get_temp_dir().'/lathe-browser-fixtures';
    $path = $directory.'/'.$name;

    if (is_file($path)) {
        return $path;
    }

    File::ensureDirectoryExists($directory);

    $contents = match ($name) {
        'avatar-3mb.png' => noisePng(1024, 1024),
        'file-12mb.bin' => str_repeat("\0", 12 * 1024 * 1024),
        default => throw new InvalidArgumentException("No fixture named {$name}."),
    };

    file_put_contents($path.'.part', $contents);
    rename($path.'.part', $path);

    return $path;
}

/**
 * An RGB PNG of random pixels, written by hand so the tests need no image library.
 */
function noisePng(int $width, int $height): string
{
    $rows = '';

    for ($y = 0; $y < $height; $y++) {
        // Filter type 0, then the row's random bytes.
        $rows .= "\0".random_bytes($width * 3);
    }

    $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));

    return "\x89PNG\r\n\x1a\n"
        .$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
        .$chunk('IDAT', (string) gzcompress($rows, 1))
        .$chunk('IEND', '');
}

/**
 * Record every alert, confirm and prompt the page opens, from now on and on every later load,
 * in window.__dialogs. A recorded dialog never opens, so the test goes on to assert it.
 */
function recordDialogs(AwaitableWebpage $page): void
{
    // One function body, so an evaluate returns nothing it would call.
    $script = <<<'JS'
        (() => {
            window.__dialogs = window.__dialogs ?? [];
            for (const kind of ['alert', 'confirm', 'prompt']) {
                window[kind] = (message) => { window.__dialogs.push(`${kind}: ${String(message)}`); };
            }
        })();
        JS;

    (fn () => $this->page)->call($page)->context()->addInitScript($script);
    $page->script($script);
}

/**
 * The page's resource requests whose path contains the given text.
 */
function requestsTo(AwaitableWebpage $page, string $text): int
{
    return (int) $page->script('performance.getEntriesByType("resource").filter((entry) => new URL(entry.name).pathname.includes('.json_encode($text).')).length');
}
