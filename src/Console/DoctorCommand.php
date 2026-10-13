<?php

namespace Lathe\Console;

use Illuminate\Console\Command;
use Lathe\Doctor\CheckResult;
use Lathe\Doctor\Doctor;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'lathe:doctor')]
class DoctorCommand extends Command
{
    protected $signature = 'lathe:doctor';

    protected $description = 'Check the Lathe setup and say what is wrong';

    public function handle(Doctor $doctor): int
    {
        $counts = [CheckResult::OK => 0, CheckResult::WARN => 0, CheckResult::FAIL => 0, CheckResult::SKIP => 0];

        foreach ($doctor->run() as $result) {
            $counts[$result->status]++;
            $this->line(str_pad($result->status, 5).$result->message);
        }

        $this->line("{$counts['OK']} OK, {$counts['WARN']} WARN, {$counts['FAIL']} FAIL");

        return $counts[CheckResult::FAIL] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
