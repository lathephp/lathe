<?php

namespace Lathe\Actions\Import;

use Lathe\Lathe;
use Lathe\Notifications\LatheNotification;

/**
 * Tells the user who started an import how it ended: its counts, a link to the failed rows
 * and, when the job failed, a way back to the run page to resume it.
 */
class ImportComplete extends LatheNotification
{
    public function __construct(Import $import)
    {
        if ($import->status === 'failed') {
            $this->error()->message(Lathe::__('Your import failed.'))->body((string) $import->exception);
        } else {
            ($import->failed_rows > 0 ? $this->warning() : $this->success())
                ->message(Lathe::__('Your import has completed.'))
                ->body(Lathe::__(':created created, :updated updated, :skipped skipped, :failed failed.', [
                    'created' => $import->created_rows,
                    'updated' => $import->updated_rows,
                    'skipped' => $import->skipped_rows,
                    'failed' => $import->failed_rows,
                ]));
        }

        $errorsUrl = $import->errorsUrl();

        if ($errorsUrl !== null) {
            $this->actionButton(Lathe::__('Download failed rows'), $errorsUrl)->openInNewTab();
        }

        if ($import->status === 'failed') {
            $this->actionButton(Lathe::__('Resume'), route('lathe.runs.show', ['type' => 'import', 'id' => $import->id]));
        }
    }
}
