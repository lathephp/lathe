<?php

namespace Lathe\Console;

use Illuminate\Console\Command;
use Lathe\Actions\BulkRun;
use Lathe\Actions\Export\Export;
use Lathe\Actions\Import\Import;
use Lathe\Lathe;
use Lathe\Uploads\PendingUpload;

class PruneCommand extends Command
{
    protected $signature = 'lathe:prune';

    protected $description = 'Delete old action events, exports, imports, draft uploads and bulk runs';

    public function handle(): int
    {
        // The action event pruner owns its own rules and prints its own count.
        $this->call('lathe:prune-action-events');

        // One literal line per kind, so the translation check sees each string.
        $this->components->info(Lathe::__(':count exports deleted.', ['count' => (new Export)->pruneAll()]));
        $this->components->info(Lathe::__(':count imports deleted.', ['count' => (new Import)->pruneAll()]));
        $this->components->info(Lathe::__(':count draft uploads deleted.', ['count' => (new PendingUpload)->pruneAll()]));
        $this->components->info(Lathe::__(':count bulk runs deleted.', ['count' => (new BulkRun)->pruneAll()]));

        return self::SUCCESS;
    }
}
