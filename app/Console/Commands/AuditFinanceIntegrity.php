<?php

namespace App\Console\Commands;

use App\Services\Finance\FinanceReconciliationService;
use Illuminate\Console\Command;

class AuditFinanceIntegrity extends Command
{
    protected $signature = 'finance:audit-integrity {--json : Print the full machine-readable audit result}';

    protected $description = 'Read-only audit of invoice, payment, and finance ledger consistency.';

    public function handle(FinanceReconciliationService $reconciliation): int
    {
        $result = $reconciliation->audit();

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $counts = $result['counts'];
            $this->line("Invoices: {$counts['invoices']}; payments: {$counts['payments']}; transactions: {$counts['transactions']}.");
            foreach ($result['findings'] as $finding) {
                $this->error((string) json_encode($finding, JSON_UNESCAPED_UNICODE));
            }
        }

        if (! $result['is_consistent']) {
            $this->error("Finance integrity audit found {$result['counts']['findings']} issue(s).");

            return self::FAILURE;
        }

        $this->info('Finance integrity audit passed.');

        return self::SUCCESS;
    }
}
