<?php

namespace Lathe\Console;

use Illuminate\Console\Command;
use Lathe\Exceptions\LatheException;
use Lathe\Resources\ResourceRegistry;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'lathe:cache')]
class CacheCommand extends Command
{
    protected $signature = 'lathe:cache';

    protected $description = 'Cache the Lathe Resource manifest';

    public function handle(ResourceRegistry $registry): int
    {
        try {
            $registry->cache();
        } catch (LatheException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Lathe Resources cached.');

        return self::SUCCESS;
    }
}
