<?php

namespace Lathe\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Lathe\Lathe;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'lathe:prune-action-events')]
class PruneActionEventsCommand extends Command
{
    /** Ids deleted per statement, so one delete never locks the table for long. */
    protected const int CHUNK = 1000;

    protected $signature = 'lathe:prune-action-events {--days= : Delete events older than this many days} {--keep= : Keep this many of the newest events of each record}';

    protected $description = 'Delete old action events, and with --keep the events beyond the newest of each record';

    public function handle(): int
    {
        $config = Lathe::config();
        $days = $this->option('days');
        $keep = $this->option('keep') ?? $config->get('action_events.prune_per_actionable');
        $cutoff = now()->subDays(is_numeric($days) ? (int) $days : $config->integer('action_events.prune_after_days'));

        $model = new (Lathe::actionEventModel());
        $connection = $model->getConnection();
        $table = $model->getTable();

        $deleted = $this->deleteInChunks($connection, $table, fn () => $connection->table($table)
            ->where('created_at', '<=', $cutoff)
            ->orderBy('id')
            ->limit(self::CHUNK)
            ->pluck('id'));

        if (is_numeric($keep) && (int) $keep > 0) {
            $keep = (int) $keep;

            // Rank each record's events newest first, the higher id first on a tie, and drop the rest.
            $deleted += $this->deleteInChunks($connection, $table, fn () => $connection->query()
                ->fromSub(
                    $connection->table($table)
                        ->whereNotNull('actionable_type')
                        ->selectRaw('id, row_number() over (partition by actionable_type, actionable_id order by created_at desc, id desc) as rn'),
                    'ranked',
                )
                ->where('rn', '>', $keep)
                ->limit(self::CHUNK)
                ->pluck('id'));
        }

        $this->components->info(Lathe::__(':count action events deleted.', ['count' => $deleted]));

        return self::SUCCESS;
    }

    /**
     * Delete the ids the picker returns, chunk by chunk, until it returns none.
     *
     * @param  callable(): Collection<int, mixed>  $pick
     */
    protected function deleteInChunks(Connection $connection, string $table, callable $pick): int
    {
        $deleted = 0;

        while (($ids = $pick())->isNotEmpty()) {
            $deleted += $connection->table($table)->whereIn('id', $ids->all())->delete();
        }

        return $deleted;
    }
}
