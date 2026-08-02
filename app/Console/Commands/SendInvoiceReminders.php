<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Finance\InvoiceService;
use App\Services\Core\NotificationService;
use Carbon\Carbon;

class SendInvoiceReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoice:reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send automated invoice reminders (H-7, H-3, H-1) for unpaid invoices.';

    protected $invoiceService;
    protected $notificationService;

    /**
     * Execute the console command.
     */
    public function handle(InvoiceService $invoiceService, NotificationService $notificationService)
    {
        $this->info('Starting invoice reminder process...');

        $invoices = $invoiceService->getAll()->where('Status', 'Waiting Payment');
        $today = Carbon::today();
        
        $count = 0;

        foreach ($invoices as $invoice) {
            if (empty($invoice['Due_Date'])) {
                continue;
            }

            $dueDate = Carbon::parse($invoice['Due_Date']);
            $diffInDays = $today->diffInDays($dueDate, false); // false for negative if overdue
            
            $daysToRemind = [7, 3, 1];
            
            if (in_array($diffInDays, $daysToRemind)) {
                $amount = number_format($invoice['Amount'] ?? 0, 0, ',', '.');
                $category = $invoice['Category'] ?? 'Pendidikan';
                $studentId = $invoice['Student_ID'] ?? '';
                
                $message = "REMINDER H-{$diffInDays}: Tagihan {$category} Anda sebesar Rp {$amount} akan jatuh tempo pada {$invoice['Due_Date']}. Mohon segera lakukan pembayaran.";
                
                $notificationService->CreateNotification(
                    $studentId,
                    'STUDENT',
                    "Reminder Tagihan (H-{$diffInDays})",
                    $message,
                    route('student.billing.index')
                );
                
                $this->info("Sent H-{$diffInDays} reminder to Student {$studentId} for Invoice {$invoice['Invoice_ID']}.");
                $count++;
            }
        }

        $this->info("Finished sending reminders. Total sent: {$count}");
    }
}
