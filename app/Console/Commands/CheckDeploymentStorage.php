<?php

namespace App\Console\Commands;

use App\Support\StorageDeploymentCheck;
use Illuminate\Console\Command;

class CheckDeploymentStorage extends Command
{
    protected $signature = 'storage:check-deployment {--local : Skip production configuration checks; audit attachments only}';
    protected $description = 'Fail deployment on non-persistent storage, unsafe upload limits or public legacy budget attachments.';

    public function handle(): int
    {
        $issues = StorageDeploymentCheck::issues(!$this->option('local'));
        foreach ($issues as $issue) $this->error($issue);
        if ($issues) return self::FAILURE;
        $this->info('Storage checks passed. Verify private bucket access and real uploads manually before release.');
        return self::SUCCESS;
    }
}
