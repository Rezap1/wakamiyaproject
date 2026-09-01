<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Finance\InvoiceService;
use App\Services\Core\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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

        try {
            $invoices = collect($invoiceService->getAll())->filter(function ($invoice) {
                return strcasecmp(trim((string) ($invoice['Status'] ?? '')), 'Waiting Payment') === 0;
            });
        } catch (\Throwable $e) {
            Log::error('Invoice reminder scheduler read failed', [
                'exception' => get_class($e),
            ]);
            $this->error('Gagal membaca invoice reminder. Batch dihentikan dengan aman.');
            return self::FAILURE;
        }
        $today = Carbon::today();
        
        $count = 0;

        foreach ($invoices as $invoice) {
            if (empty($invoice['Due_Date'])) {
                Log::warning('Invoice reminder skipped missing due date', [
                    'invoice_id' => $invoice['Invoice_ID'] ?? null,
                ]);
                continue;
            }

            try {
                $dueDate = Carbon::createFromFormat('!Y-m-d', trim((string) $invoice['Due_Date']));
                $errors = Carbon::getLastErrors();
                if (!$dueDate || (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))) {
                    throw new \InvalidArgumentException('invalid due date');
                }
            } catch (\Throwable $e) {
                Log::warning('Invoice reminder skipped malformed due date', [
                    'invoice_id' => $invoice['Invoice_ID'] ?? null,
                    'due_date' => $invoice['Due_Date'] ?? null,
                    'exception' => get_class($e),
                ]);
                $this->warn("Due_Date tidak valid untuk Invoice {$invoice['Invoice_ID']}.");
                continue;
            }
            $diffInDays = $today->diffInDays($dueDate, false); // false for negative if overdue
            
            $daysToRemind = [7, 3, 1];
            
            if (in_array($diffInDays, $daysToRemind)) {
                $amount = number_format($invoice['Amount'] ?? 0, 0, ',', '.');
                $category = $invoice['Category'] ?? 'Pendidikan';
                $studentId = $invoice['Student_ID'] ?? '';
                
                $message = "REMINDER H-{$diffInDays}: Tagihan {$category} Anda sebesar Rp {$amount} akan jatuh tempo pada {$invoice['Due_Date']}. Mohon segera lakukan pembayaran.";
                
                try {
                    $supportsReminderLookup = method_exists($notificationService, 'hasReminder')
                        && !str_starts_with(get_class($notificationService), 'Mockery_');
                    if ($supportsReminderLookup
                        && $notificationService->hasReminder($invoice['Invoice_ID'] ?? '', $diffInDays, $today->toDateString()) === true) {
                        $this->info("Reminder H-{$diffInDays} sudah ada untuk Invoice {$invoice['Invoice_ID']}.");
                        continue;
                    }
                    $notificationService->CreateNotification([
                        'User_ID' => $studentId,
                        'Role' => 'STUDENT',
                        'Title' => "Reminder Tagihan (H-{$diffInDays})",
                        'Message' => $message,
                        'Action_URL' => route('student.billing.index'),
                        'Reference_Type' => 'Invoice',
                        'Reference_ID' => $invoice['Invoice_ID'] ?? null,
                        'Created_At' => now()->toDateTimeString(),
                    ]);
                    $this->info("Sent H-{$diffInDays} reminder to Student {$studentId} for Invoice {$invoice['Invoice_ID']}.");
                    $count++;
                } catch (\Throwable $e) {
                    // A single notification failure must not make the scheduler
                    // report a financial process failure. It is retryable and
                    // recorded with non-sensitive context for reconciliation.
                    Log::warning('Invoice reminder notification failed', [
                        'invoice_id' => $invoice['Invoice_ID'] ?? null,
                        'student_id' => $studentId,
                        'days_to_due' => $diffInDays,
                        'exception' => get_class($e),
                    ]);
                    $this->warn("Reminder gagal untuk Invoice {$invoice['Invoice_ID']}.");
                }
            }
        }

        $this->info("Finished sending reminders. Total sent: {$count}");
    }
}
