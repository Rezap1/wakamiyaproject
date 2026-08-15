<?php

namespace App\Services\Core;

use App\Services\Core\SystemSettingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailDeliveryService
{
    protected $settingService;

    public function __construct(SystemSettingService $settingService)
    {
        $this->settingService = $settingService;
    }

    /**
     * Get configured sender address and name.
     */
    public function getSenderConfig(): array
    {
        $company = $this->settingService->getCompanyProfile();
        
        $fromAddress = $this->settingService->get(
            'EMAIL_FROM_ADDRESS', 
            $company['company']['email'] ?? config('mail.from.address', 'admin@wakamiya.ac.id')
        );

        $fromName = $this->settingService->get(
            'EMAIL_FROM_NAME', 
            $company['company']['name'] ?? config('mail.from.name', 'WAKAMIYA MANAGEMENT SYSTEM')
        );

        return [
            'from_address' => $fromAddress ?: 'admin@wakamiya.ac.id',
            'from_name' => $fromName ?: 'WAKAMIYA MANAGEMENT SYSTEM',
            'status' => '🟢 Email Sender Configured'
        ];
    }

    /**
     * Send test email to specified recipient (e.g. rezagaming800@gmail.com).
     */
    public function sendTestEmail(string $recipientEmail): array
    {
        $recipientEmail = trim($recipientEmail);

        if (empty($recipientEmail) || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => 'Alamat email penerima test tidak valid.'
            ];
        }

        $sender = $this->getSenderConfig();
        $subject = 'WMS Email Delivery Test';
        $timestamp = now()->format('d M Y H:i:s T');

        $bodyText = "WAKAMIYA MANAGEMENT SYSTEM\n\n" .
            "Email delivery test berhasil dikirim.\n" .
            "Pengirim: {$sender['from_name']} <{$sender['from_address']}>\n" .
            "Waktu: {$timestamp}\n";

        try {
            Mail::raw($bodyText, function ($message) use ($recipientEmail, $sender, $subject) {
                $message->to($recipientEmail)
                        ->from($sender['from_address'], $sender['from_name'])
                        ->subject($subject);
            });

            return [
                'success' => true,
                'message' => "Email delivery test berhasil diproses untuk {$recipientEmail}.",
                'recipient' => $recipientEmail,
                'sender' => $sender
            ];
        } catch (\Throwable $e) {
            Log::error('EmailDeliveryService Test Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Gagal dispatch test email: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send Document Invoice / Kwitansi email with PDF attachment.
     */
    public function sendDocumentEmail(array $payload): array
    {
        $recipientEmail = trim($payload['client_email'] ?? '');
        $docNumber = $payload['doc_number'] ?? 'INV-DOCUMENT';
        $sourceType = $payload['source_type'] ?? 'manual_invoice';

        if (empty($recipientEmail)) {
            return [
                'success' => false,
                'message' => ($sourceType === 'student_invoice') ? 'Email siswa tidak tersedia.' : 'Email client tidak boleh kosong.'
            ];
        }

        $sender = $this->getSenderConfig();
        $subject = "Tagihan Invoice {$docNumber} — " . $sender['from_name'];

        $clientName = $payload['client_name'] ?? 'Pelanggan';
        $issueDate = $payload['issue_date'] ?? date('Y-m-d');
        $dueDate = $payload['due_date'] ?? date('Y-m-d', strtotime('+14 days'));
        $grandTotal = 'Rp ' . number_format((float)($payload['grand_total'] ?? $payload['subtotal'] ?? 0), 0, ',', '.');

        $bodyText = "Yth. {$clientName},\n\n" .
            "Berikut kami sampaikan dokumen tagihan:\n\n" .
            "Invoice : {$docNumber}\n" .
            "Tanggal : {$issueDate}\n" .
            "Jatuh Tempo : {$dueDate}\n" .
            "Total : {$grandTotal}\n\n" .
            "Silakan melihat lampiran PDF untuk rincian lengkap.\n\n" .
            "Hormat kami,\n" .
            "{$sender['from_name']}\n";

        // Generate PDF Binary
        try {
            $viewName = (($payload['doc_type'] ?? 'invoice') === 'kwitansi')
                ? 'pdf.smart_generator_kwitansi'
                : 'pdf.smart_generator_invoice';

            $pdf = Pdf::loadView($viewName, ['data' => $payload]);
            $pdf->setPaper('A4', 'portrait');
            $pdf->getDomPDF()->getOptions()->set([
                'defaultFont' => 'Helvetica',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true
            ]);

            $pdfContent = $pdf->output();
            $filename = (($payload['doc_type'] ?? 'invoice') === 'kwitansi' ? 'Kwitansi_' : 'Invoice_') . $docNumber . '.pdf';

            Mail::raw($bodyText, function ($message) use ($recipientEmail, $sender, $subject, $pdfContent, $filename) {
                $message->to($recipientEmail)
                        ->from($sender['from_address'], $sender['from_name'])
                        ->subject($subject)
                        ->attachData($pdfContent, $filename, ['mime' => 'application/pdf']);
            });

            return [
                'success' => true,
                'message' => "Email pemberitahuan dokumen {$docNumber} telah dikirim ke {$recipientEmail}."
            ];
        } catch (\Throwable $e) {
            Log::error('EmailDeliveryService Document Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Gagal mengirim email dokumen: ' . $e->getMessage()
            ];
        }
    }
}
