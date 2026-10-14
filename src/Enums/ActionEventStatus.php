<?php

namespace Lathe\Enums;

/**
 * Where an action run stands for one record.
 */
enum ActionEventStatus: string
{
    case Waiting = 'waiting';
    case Running = 'running';
    case Finished = 'finished';
    case Failed = 'failed';

    /**
     * The statuses a row never leaves.
     *
     * @return list<self>
     */
    public static function terminal(): array
    {
        return [self::Finished, self::Failed];
    }
}
