<?php

namespace Lathe\Console;

use Illuminate\Console\Command;
use Lathe\Resources\ResourceRegistry;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'lathe:clear')]
class ClearCommand extends Command
{
    protected $signature = 'lathe:clear';

    protected $description = 'Remove the cached Lathe Resource manifest';

    public function handle(ResourceRegistry $registry): int
    {
        $registry->clearCache();

        $this->components->info('Lathe Resource cache cleared.');

        return self::SUCCESS;
    }
}
