<?php

namespace Lathe\Actions\Export;

use Lathe\Enums\Icon;
use Lathe\Lathe;
use Lathe\Notifications\LatheNotification;

/**
 * Tells the user who started a queued export that its file is ready, with a Download
 * button that opens a new tab, or that it failed.
 */
class ExportReady extends LatheNotification
{
    public function __construct(Export $export)
    {
        if ($export->failed_at !== null) {
            $this->message(Lathe::__('Your export failed.'))->error();

            return;
        }

        $this->message(Lathe::__('Your export is ready for download.'))
            ->success()
            ->actionButton(Lathe::__('Download'), $export->downloadUrl(), Icon::Download)
            ->openInNewTab();
    }
}
