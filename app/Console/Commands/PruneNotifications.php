<?php

namespace App\Console\Commands;

use App\Services\Core\NotificationRetentionService;
use Illuminate\Console\Command;

class PruneNotifications extends Command
{
    protected $signature = 'notifications:prune';

    protected $description = 'Delete MASTER_NOTIFICATION rows older than 30 days';

    public function handle(NotificationRetentionService $retention): int
    {
        $result = $retention->prune();
        $this->info(sprintf(
            'Notification prune complete: %d deleted, %d malformed skipped (cutoff %s WIB).',
            $result['deleted'],
            $result['malformed_skipped'],
            $result['cutoff'],
        ));

        return self::SUCCESS;
    }
}
