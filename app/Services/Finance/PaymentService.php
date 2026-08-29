<?php

namespace App\Services\Finance;

use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;
use App\Interfaces\GoogleSheets\AccountRepositoryInterface;
use App\Interfaces\GoogleSheets\TransactionRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use Illuminate\Support\Facades\Cache;
use Exception;

class PaymentService
{
    protected $paymentRepository;
    protected $invoiceRepository;
    protected $studentRepository;
    protected $companyRepository;
    protected $accountRepository;
    protected $transactionRepository;
    protected $enterpriseEvent;

    public function __construct(
        PaymentRepositoryInterface $paymentRepository,
        InvoiceRepositoryInterface $invoiceRepository,
        StudentRepositoryInterface $studentRepository,
        CompanyRepositoryInterface $companyRepository,
        AccountRepositoryInterface $accountRepository,
        TransactionRepositoryInterface $transactionRepository,
        EnterpriseEventService $enterpriseEvent
    ) {
        $this->paymentRepository = $paymentRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->studentRepository = $studentRepository;
        $this->companyRepository = $companyRepository;
        $this->accountRepository = $accountRepository;
        $this->transactionRepository = $transactionRepository;
        $this->enterpriseEvent = $enterpriseEvent;
    }

    public function getAll() 
    { 
        $payments = collect($this->paymentRepository->getAll())->where('Is_Active', '!=', 'FALSE')->values();
        $user = auth()->user();
        
        // Resolve the role case-insensitively; RoleMiddleware normalizes role names
        // from the SSOT, while legacy user rows may still contain mixed casing.
        if ($user && strtoupper(trim((string) ($user->Role ?? ''))) === 'STUDENT') {
            $student = collect($this->studentRepository->fetchAll())->firstWhere('User_ID', $user->User_ID);
            if ($student) {
                return $payments->where('Student_ID', $student['Student_ID'])->values();
            }
            return collect();
        }
        
        return $payments;
    }

    public function getById($id) { 
        return $this->paymentRepository->getById($id); 
    }

    public function getPaymentReceiptData(string $paymentId, bool $allowPublicVerification = false): array
    {
        $payment = $this->getById($paymentId);
        if (!$payment) {
            throw new Exception("Kuitansi pembayaran #{$paymentId} tidak ditemukan.");
        }

        // Public access is reserved for the signed verification endpoint.
        $user = auth()->user();
        if (!$allowPublicVerification) {
            if (!$user) {
                throw new Exception("Akses Ditolak: Identitas pengguna tidak dapat dipastikan.");
            }

            $role = strtoupper(trim((string) ($user->Role ?? '')));
            if ($role === 'STUDENT') {
                $student = collect($this->studentRepository->fetchAll())->firstWhere('User_ID', $user->User_ID);
                if (!$student || ($payment['Student_ID'] ?? '') !== ($student['Student_ID'] ?? '')) {
                    throw new Exception("Akses Ditolak: Kuitansi #{$paymentId} bukan milik akun Anda.");
                }
            } elseif (!in_array($role, ['ADMINISTRATOR', 'FINANCE'], true)) {
                throw new Exception("Akses Ditolak: Role pengguna tidak diizinkan mengakses kuitansi.");
            }
        }

        $invoiceService = app(InvoiceService::class);
        $invoice = null;
        if (!empty($payment['Invoice_ID'])) {
            $invoice = $invoiceService->getById($payment['Invoice_ID']);
        }

        // Customer Lookup
        $customerName = '-';
        $customerCode = '-';
        $customerType = $payment['Payment_Type'] ?? ($invoice['Invoice_Type'] ?? 'STUDENT');

        if ($customerType === 'STUDENT' && !empty($payment['Student_ID'])) {
            $student = $this->studentRepository->findById($payment['Student_ID']);
            $customerName = $student['Full_Name'] ?? $payment['Student_ID'];
            $customerCode = $student['Student_Number'] ?? $payment['Student_ID'];
        } elseif ($customerType === 'COMPANY' && !empty($payment['Company_ID'])) {
            $company = $this->companyRepository->findById($payment['Company_ID']);
            $customerName = $company['Company_Name'] ?? $payment['Company_ID'];
            $customerCode = $company['Company_Code'] ?? $payment['Company_ID'];
        } else {
            $customerName = $payment['Sender_Name'] ?? ($payment['Student_ID'] ?? 'Pelanggan');
            $customerCode = $payment['Student_ID'] ?? $payment['Company_ID'] ?? '-';
        }

        // Receiving Account Lookup
        $receivingAccount = $this->resolvePaymentAccount($payment['Payment_Method'] ?? 'TRANSFER');

        // Financial Balances Breakdown
        $invoiceAmount = (float)($invoice['Amount'] ?? 0);
        $currentPaymentAmount = (float)($payment['Amount_Paid'] ?? 0);
        
        $allPayments = collect($this->paymentRepository->getAll())
            ->where('Invoice_ID', $payment['Invoice_ID'] ?? '')
            ->where('Status', 'Verified');
        
        $totalVerifiedSoFar = (float) $allPayments->sum('Amount_Paid');
        $prevVerified = max(0.0, $totalVerifiedSoFar - $currentPaymentAmount);
        $remainingBalance = max(0.0, $invoiceAmount - $totalVerifiedSoFar);

        // Public Verification URL
        $verificationUrl = \App\Helpers\PublicVerificationUrl::make('payments.verify-receipt-public', $paymentId);

        // QR Code SVG
        $qrCodeSvg = null;
        if (class_exists('\SimpleSoftwareIO\QrCode\Facades\QrCode')) {
            try {
                $qrCodeSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::size(90)->margin(1)->generate($verificationUrl);
            } catch (\Exception $e) {
                $qrCodeSvg = null;
            }
        }

        $systemSettingService = app(\App\Services\Core\SystemSettingService::class);
        $companyProfile = $systemSettingService->getCompanyProfile();

        return [
            'companyProfile' => $companyProfile,
            'company' => $companyProfile['company'],
            'bank' => $companyProfile['bank'],
            'document' => $companyProfile['document'],
            'payment' => $payment,
            'invoice' => $invoice,
            'customer' => [
                'type' => $customerType,
                'name' => $customerName,
                'code' => $customerCode
            ],
            'receivingAccount' => $receivingAccount,
            'balances' => [
                'invoiceAmount' => $invoiceAmount,
                'prevVerified' => $prevVerified,
                'currentPayment' => $currentPaymentAmount,
                'remainingBalance' => $remainingBalance
            ],
            'verificationUrl' => $verificationUrl,
            'qrCodeSvg' => $qrCodeSvg
        ];
    }

    public function getReceiptDocumentData(string $paymentId, bool $allowPublicVerification = false): array
    {
        return $this->getPaymentReceiptData($paymentId, $allowPublicVerification);
    }

    public function generateReceiptNumber($type = 'STUDENT')
    {
        $prefix = $type === 'COMPANY' ? 'RCT-CORP' : 'RCT-STU';
        $year = date('Y');
        
        $counterKey = 'receipt_counter_' . $prefix . '_' . $year;
        $lockKey = 'receipt_write_lock';

        return Cache::lock($lockKey, 10)->block(5, function () use ($prefix, $year, $counterKey) {
            if (!Cache::has($counterKey)) {
                $all = $this->paymentRepository->getAll();
                $count = collect($all)->filter(function($item) use ($prefix, $year) {
                    return str_starts_with($item['Payment_ID'] ?? '', "{$prefix}-{$year}-");
                })->count();
                Cache::forever($counterKey, $count);
            }
            
            $nextNumber = Cache::increment($counterKey);
            return sprintf("%s-%s-%06d", $prefix, $year, $nextNumber);
        });
    }

    public function resolvePaymentAccount(string $paymentMethod = 'TRANSFER', ?string $explicitAccountId = null): string
    {
        $allAccounts = collect($this->accountRepository->fetchAll())->where('Is_Active', '!=', 'FALSE');
        
        if (!empty($explicitAccountId)) {
            $matched = $allAccounts->firstWhere('Account_ID', $explicitAccountId) ?? $allAccounts->firstWhere('Account_Code', $explicitAccountId);
            if ($matched) {
                return $matched['Account_Code'] ?? $matched['Account_ID'];
            }
        }

        $assets = $allAccounts->filter(function($acc) {
            $cat = strtoupper($acc['Account_Category'] ?? '');
            return str_contains($cat, 'ASSET') || str_contains($cat, 'ASET');
        });

        $methodUpper = strtoupper(trim($paymentMethod));
        
        if ($methodUpper === 'CASH' || $methodUpper === 'TUNAI') {
            $cashAcc = $assets->first(function($acc) {
                $name = strtolower($acc['Account_Name'] ?? '');
                $code = $acc['Account_Code'] ?? '';
                return str_contains($name, 'kas') || str_contains($name, 'cash') || $code === '101';
            });
            if ($cashAcc) return $cashAcc['Account_Code'] ?? $cashAcc['Account_ID'];
        } else {
            $bankAcc = $assets->first(function($acc) {
                $name = strtolower($acc['Account_Name'] ?? '');
                $code = $acc['Account_Code'] ?? '';
                return str_contains($name, 'bank') || str_contains($name, 'bsi') || $code === '102';
            });
            if ($bankAcc) return $bankAcc['Account_Code'] ?? $bankAcc['Account_ID'];
        }

        $fallbackAsset = $assets->first();
        if ($fallbackAsset) {
            return $fallbackAsset['Account_Code'] ?? $fallbackAsset['Account_ID'];
        }

        return ($methodUpper === 'CASH' || $methodUpper === 'TUNAI') ? '101' : '102';
    }

    public function create(array $data)
    {
        return $this->submitPayment($data);
    }

    public function submitPayment(array $data)
    {
        $invoiceId = $data['Invoice_ID'] ?? null;
        if (empty($invoiceId)) {
            throw new Exception("ID Tagihan (Invoice_ID) wajib diisi.");
        }

        return Cache::lock("payment_submit_{$invoiceId}", 30)->block(5, function () use ($data, $invoiceId) {
            $invoiceService = app(InvoiceService::class);
            $invoice = $invoiceService->getById($invoiceId);
        if (!$invoice || ($invoice['Is_Active'] ?? 'TRUE') === 'FALSE') {
            throw new Exception("Tagihan #{$invoiceId} tidak ditemukan atau sedang tidak aktif.");
        }

        // 1. IDOR Ownership Verification for STUDENT role
        $user = auth()->user();
        if ($user && strtoupper(trim((string) ($user->Role ?? ''))) === 'STUDENT') {
            $student = collect($this->studentRepository->fetchAll())->firstWhere('User_ID', $user->User_ID);
            if (!$student || ($invoice['Student_ID'] ?? '') !== $student['Student_ID']) {
                throw new Exception("Akses Ditolak: Tagihan #{$invoiceId} bukan milik akun Anda.");
            }
            $data['Student_ID'] = $student['Student_ID'];
        } else {
            $data['Student_ID'] = $data['Student_ID'] ?? $invoice['Student_ID'] ?? null;
        }

        // 2. Invoice State Protection
        $invoiceStatus = $invoice['Status'] ?? 'Draft';
        if (strcasecmp($invoiceStatus, 'Draft') === 0) {
            throw new Exception("Tagihan #{$invoiceId} masih berstatus Draft dan belum diterbitkan.");
        }
        if (strcasecmp($invoiceStatus, 'Cancelled') === 0) {
            throw new Exception("Tagihan #{$invoiceId} telah Dibatalkan dan tidak dapat menerima pembayaran.");
        }
        if (strcasecmp($invoiceStatus, 'Paid') === 0) {
            throw new Exception("Tagihan #{$invoiceId} telah Lunas (PAID) dan tidak dapat menerima pembayaran lagi.");
        }

        // 3. Overpayment Guard
        $remainingAmount = $invoiceService->calculateRemainingAmount($invoice);
        if ($remainingAmount <= 0) {
            throw new Exception("Tagihan #{$invoiceId} tidak memiliki sisa piutang (Sisa Rp 0).");
        }

        $amountPaid = (float) ($data['Amount_Paid'] ?? 0);
        if ($amountPaid <= 0) {
            throw new Exception("Nominal pembayaran harus lebih besar dari Rp 0.");
        }
        if ($amountPaid > $remainingAmount) {
            throw new Exception("Nominal pembayaran (Rp " . number_format($amountPaid, 0, ',', '.') . ") melebihi sisa tagihan (Rp " . number_format($remainingAmount, 0, ',', '.') . ").");
        }

        // 4. Duplicate Payment Protection (Prevent parallel submissions while waiting verification)
        $allPayments = $this->paymentRepository->getAll();
        $pendingPayment = collect($allPayments)
            ->where('Invoice_ID', $invoiceId)
            ->where('Status', 'Waiting Verification')
            ->first();

        if ($pendingPayment) {
            throw new Exception("Pembayaran untuk tagihan #{$invoiceId} sedang menunggu verifikasi dari bagian Keuangan. Harap tunggu hingga proses verifikasi selesai.");
        }

        // Assign default receipt & metadata
        $data['Payment_Type'] = $data['Payment_Type'] ?? ($invoice['Invoice_Type'] ?? 'STUDENT');
        if (empty($data['Payment_ID'])) {
            $data['Payment_ID'] = $this->generateReceiptNumber($data['Payment_Type']);
        }
        
        $data['Status'] = 'Waiting Verification';
        $data['Payment_Date'] = $data['Payment_Date'] ?? now()->toDateString();
        $data['Created_At'] = now()->toDateTimeString();
        $data['Is_Active'] = 'TRUE';

        $res = $this->paymentRepository->create($data);
        if ($res === false || $res === null) {
            throw new Exception("Gagal menyimpan pembayaran {$data['Payment_ID']}.");
        }
        $this->paymentRepository->clearCache();
        
        if (!empty($data['Student_ID'])) {
            Cache::forget("student_billing_{$data['Student_ID']}");
        }
        Cache::forget('finance_dashboard');
        Cache::forget('dashboard_finance');

        $this->enterpriseEvent->dispatch(
            'FINANCE', 
            'CREATE', 
            'PAYMENT', 
            $data['Payment_ID'], 
            \App\Support\ActorIdentity::required(), 
            ['FINANCE'], 
            !empty($data['Student_ID']) ? [$data['Student_ID']] : [], 
            $data
        );
        
            return $res;
        });
    }

    public function verifyPayment($paymentId, $verifiedBy, $status, $notes = '', $explicitAccountId = null)
    {
        $lockKey = "payment_verify_{$paymentId}";
        
        return Cache::lock($lockKey, 10)->block(5, function () use ($paymentId, $verifiedBy, $status, $notes, $explicitAccountId) {
            if (!in_array($status, ['Verified', 'Rejected', 'Need Revision'], true)) {
                throw new Exception("Status verifikasi pembayaran tidak valid.");
            }

            $payment = $this->getById($paymentId);
            if (!$payment) {
                throw new Exception("Payment #{$paymentId} tidak ditemukan.");
            }

            $currentStatus = trim((string) ($payment['Status'] ?? 'Waiting Verification'));
            if ($currentStatus === $status) {
                if ($status === 'Verified') {
                    \App\Support\ActorIdentity::required();
                    $this->reconcileVerifiedPayment($paymentId, $payment, $explicitAccountId);
                }
                return $payment;
            }

            if (strcasecmp($currentStatus, 'Verified') === 0) {
                throw new Exception("Pembayaran #{$paymentId} sudah terverifikasi dan tidak dapat diubah statusnya.");
            }

            if (strcasecmp($currentStatus, 'Rejected') === 0) {
                throw new Exception("Pembayaran #{$paymentId} sudah ditolak. Silakan minta pembayaran baru dari siswa.");
            }

            $actorId = \App\Support\ActorIdentity::required();

            if ($status === 'Verified') {
                $invoiceId = $payment['Invoice_ID'] ?? null;
                if (empty($invoiceId)) {
                    throw new Exception("Pembayaran #{$paymentId} tidak memiliki Invoice_ID yang valid.");
                }

                $invoiceService = app(InvoiceService::class);
                $invoice = $invoiceService->getById($invoiceId);
                if (!$invoice) {
                    throw new Exception("Tagihan #{$invoiceId} tidak ditemukan.");
                }

                $invoiceStatus = trim((string) ($invoice['Status'] ?? 'Draft'));
                if (in_array(strtolower($invoiceStatus), ['draft', 'cancelled'], true)) {
                    throw new Exception("Tagihan #{$invoiceId} berstatus {$invoiceStatus} dan tidak dapat menerima verifikasi pembayaran.");
                }

                $invoiceAmount = (float) ($invoice['Amount'] ?? 0);
                $paymentAmount = (float) ($payment['Amount_Paid'] ?? 0);
                $verifiedBefore = (float) collect($this->paymentRepository->getAll())
                    ->where('Invoice_ID', $invoiceId)
                    ->where('Status', 'Verified')
                    ->reject(fn ($item) => ($item['Payment_ID'] ?? '') === $paymentId)
                    ->sum('Amount_Paid');

                if ($paymentAmount <= 0) {
                    throw new Exception("Nominal pembayaran #{$paymentId} harus lebih besar dari Rp 0.");
                }

                if (($verifiedBefore + $paymentAmount) > ($invoiceAmount + 0.001)) {
                    $remaining = max(0.0, $invoiceAmount - $verifiedBefore);
                    throw new Exception(
                        "Verifikasi pembayaran #{$paymentId} ditolak karena nominal Rp "
                        . number_format($paymentAmount, 0, ',', '.')
                        . " melebihi sisa tagihan Rp "
                        . number_format($remaining, 0, ',', '.')
                        . "."
                    );
                }
            }

            $data = [
                'Status' => $status,
                'Verified_By' => $actorId,
                'Verified_At' => now()->toDateTimeString(),
                'Notes' => $notes,
                'Updated_At' => now()->toDateTimeString()
            ];

            $res = $this->paymentRepository->update($paymentId, $data);
            if (!$res) {
                throw new Exception("Gagal menyimpan status pembayaran #{$paymentId}.");
            }
            $this->paymentRepository->clearCache();

            if (!empty($payment['Student_ID'])) {
                Cache::forget("student_billing_{$payment['Student_ID']}");
            }
            Cache::forget('finance_dashboard');
            Cache::forget('dashboard_finance');

            if ($status === 'Verified') {
                $this->reconcileVerifiedPayment($paymentId, array_merge($payment, $data), $explicitAccountId);

                $this->enterpriseEvent->dispatch(
                    'FINANCE', 
                    'VERIFY', 
                    'PAYMENT', 
                    $paymentId, 
                    $actorId,
                    ['STUDENT'], 
                    !empty($payment['Student_ID']) ? [$payment['Student_ID']] : [], 
                    ['Status' => $status, 'Notes' => $notes]
                );
            } elseif (in_array($status, ['Rejected', 'Need Revision'])) {
                $this->enterpriseEvent->dispatch(
                    'FINANCE', 
                    'UPDATE', 
                    'PAYMENT', 
                    $paymentId, 
                    $actorId,
                    ['STUDENT'], 
                    !empty($payment['Student_ID']) ? [$payment['Student_ID']] : [], 
                    ['Status' => $status, 'Notes' => $notes]
                );
            }

            return $res;
        });
    }

    protected function getVerifiedPaymentTotalForInvoice(string $invoiceId): float
    {
        $all = $this->paymentRepository->getAll();
        return (float) collect($all)
            ->where('Invoice_ID', $invoiceId)
            ->where('Status', 'Verified')
            ->sum('Amount_Paid');
    }

    private function reconcileVerifiedPayment(string $paymentId, array $payment, ?string $explicitAccountId = null): void
    {
        $invoiceId = trim((string) ($payment['Invoice_ID'] ?? ''));
        if ($invoiceId === '') {
            throw new Exception("Pembayaran #{$paymentId} tidak memiliki Invoice_ID yang valid.");
        }

        $invoiceService = app(InvoiceService::class);
        $invoice = $invoiceService->getById($invoiceId);
        if (!$invoice) {
            throw new Exception("Tagihan #{$invoiceId} tidak ditemukan saat rekonsiliasi pembayaran.");
        }

        $verifiedPayments = $this->getVerifiedPaymentTotalForInvoice($invoiceId);
        $invoiceAmount = (float) ($invoice['Amount'] ?? 0);
        $invoiceStatus = $verifiedPayments >= $invoiceAmount ? 'Paid' : 'Partial Paid';
        $updated = $this->invoiceRepository->update($invoiceId, [
            'Status' => $invoiceStatus,
            'Updated_At' => now()->toDateTimeString(),
        ]);
        if (!$updated) {
            throw new Exception("Gagal merekonsiliasi status tagihan #{$invoiceId}.");
        }
        $this->invoiceRepository->clearCache();

        $existingTransaction = collect($this->transactionRepository->fetchAll())
            ->where('Reference_Type', 'Payment')
            ->where('Reference_ID', $paymentId)
            ->first();

        if (!$existingTransaction) {
            $targetAccountId = $this->resolvePaymentAccount($payment['Payment_Method'] ?? 'TRANSFER', $explicitAccountId);
            app(TransactionService::class)->create([
                'Transaction_Date' => now()->format('Y-m-d'),
                'Account_ID' => $targetAccountId,
                'Type' => 'Income',
                'Category' => 'Payment Receipt',
                'Amount' => (float) ($payment['Amount_Paid'] ?? 0),
                'Reference_Type' => 'Payment',
                'Reference_ID' => $paymentId,
                'Description' => "Pembayaran Verifikasi Kuitansi #{$paymentId} untuk Invoice #{$invoiceId}",
            ]);
        }
    }

    public function deletePayment($paymentId)
    {
        $payment = $this->getById($paymentId);
        if (!$payment) {
            throw new Exception("Payment not found");
        }

        if (strcasecmp(trim((string) ($payment['Status'] ?? '')), 'Verified') === 0) {
            throw new Exception("Pembayaran #{$paymentId} sudah terverifikasi dan tidak dapat dihapus karena sudah menjadi transaksi kas.");
        }

        $deleted = $this->paymentRepository->delete($paymentId);
        if ($deleted === false || $deleted === null) {
            throw new Exception("Gagal menghapus pembayaran #{$paymentId}.");
        }
        $this->paymentRepository->clearCache();
        
        if (!empty($payment['Student_ID'])) {
            Cache::forget("student_billing_{$payment['Student_ID']}");
        }
        Cache::forget('finance_dashboard');
        Cache::forget('dashboard_finance');

        $this->enterpriseEvent->dispatch(
            'FINANCE',
            'DELETE',
            'PAYMENT',
            $paymentId,
            \App\Support\ActorIdentity::required(),
            ['FINANCE'],
            !empty($payment['Student_ID']) ? [$payment['Student_ID']] : [],
            []
        );
        
        return true;
    }
}
