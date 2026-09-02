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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;
use App\Support\Finance\Money;
use App\Support\Finance\PaymentStatus;
use App\Exceptions\FinancialIntegrityException;
use App\Exceptions\DuplicatePrimaryKeyException;

class PaymentService
{
    protected $paymentRepository;
    protected $invoiceRepository;
    protected $studentRepository;
    protected $companyRepository;
    protected $accountRepository;
    protected $transactionRepository;
    protected $enterpriseEvent;
    protected $transactionService;

    public function __construct(
        PaymentRepositoryInterface $paymentRepository,
        InvoiceRepositoryInterface $invoiceRepository,
        StudentRepositoryInterface $studentRepository,
        CompanyRepositoryInterface $companyRepository,
        AccountRepositoryInterface $accountRepository,
        TransactionRepositoryInterface $transactionRepository,
        EnterpriseEventService $enterpriseEvent,
        ?TransactionService $transactionService = null
    ) {
        $this->paymentRepository = $paymentRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->studentRepository = $studentRepository;
        $this->companyRepository = $companyRepository;
        $this->accountRepository = $accountRepository;
        $this->transactionRepository = $transactionRepository;
        $this->enterpriseEvent = $enterpriseEvent;
        $this->transactionService = $transactionService;
    }

    public function getAll() 
    { 
        $payments = collect($this->paymentRepository->getAll())->where('Is_Active', '!=', 'FALSE')->values();
        $user = auth()->user();
        
        // Resolve the role case-insensitively; RoleMiddleware normalizes role names
        // from the SSOT, while legacy user rows may still contain mixed casing.
        if ($user && $this->authenticatedRoleName($user) === 'STUDENT') {
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

            $role = $this->authenticatedRoleName($user);
            if ($role === 'STUDENT') {
                $student = collect($this->studentRepository->fetchAll())->firstWhere('User_ID', $user->User_ID);
                if (!$student || ($payment['Student_ID'] ?? '') !== ($student['Student_ID'] ?? '')) {
                    throw new Exception("Akses Ditolak: Kuitansi #{$paymentId} bukan milik akun Anda.");
                }
            } elseif (!in_array($role, ['MASTER', 'ADMINISTRATOR', 'FINANCE'], true)) {
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
        $invoiceAmount = Money::value($invoice['Amount'] ?? 0, 'Invoice Amount');
        $currentPaymentAmount = Money::value($payment['Amount_Paid'] ?? 0, 'Nominal pembayaran');
        
        $allPayments = collect(method_exists($this->paymentRepository, 'getAllFresh') ? $this->paymentRepository->getAllFresh() : $this->paymentRepository->getAll())
            ->where('Invoice_ID', $payment['Invoice_ID'] ?? '')
            ->filter(fn ($row) => PaymentStatus::verified($row['Status'] ?? null));
        
        $totalVerifiedSoFar = (float) $allPayments->sum(fn ($item) => Money::value($item['Amount_Paid'] ?? 0, 'Nominal pembayaran'));
        $prevVerified = max(0.0, round($totalVerifiedSoFar - $currentPaymentAmount, Money::SCALE));
        $remainingBalance = max(0.0, round($invoiceAmount - $totalVerifiedSoFar, Money::SCALE));

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

        return Cache::lock($lockKey, 120)->block(15, function () use ($prefix, $year, $counterKey) {
            $rows = method_exists($this->paymentRepository, 'getAllFresh')
                ? $this->paymentRepository->getAllFresh()
                : $this->paymentRepository->getAll();
            $pattern = '/^' . preg_quote($prefix, '/') . '-' . $year . '-(\d+)$/i';
            $maxPersisted = 0;
            $existing = [];
            foreach ($rows as $item) {
                foreach ([$item['Receipt_Number'] ?? null, $item['Payment_ID'] ?? null] as $rawId) {
                    $id = trim((string) $rawId);
                    if (preg_match($pattern, $id, $m) === 1) {
                        $suffix = (int) $m[1];
                        $maxPersisted = max($maxPersisted, $suffix);
                        $existing[strtolower($id)] = true;
                    }
                }
            }
            $cached = max(0, (int) Cache::get($counterKey, 0));
            $candidate = max($maxPersisted, $cached) + 1;
            for ($attempt = 1; $attempt <= 5; $attempt++) {
                $receipt = sprintf('%s-%s-%06d', $prefix, $year, $candidate);
                if (!isset($existing[strtolower($receipt)])) {
                    Cache::forever($counterKey, $candidate);
                    return $receipt;
                }
                $candidate++;
            }
            throw new FinancialIntegrityException("Tidak dapat mengalokasikan nomor receipt aman untuk {$prefix}-{$year}.");
        });
    }

    public function resolvePaymentAccount(string $paymentMethod = 'TRANSFER', ?string $explicitAccountId = null): string
    {
        $accountRows = method_exists($this->accountRepository, 'fetchAllFresh')
            ? $this->accountRepository->fetchAllFresh()
            : $this->accountRepository->fetchAll();
        $allAccounts = collect($accountRows)->where('Is_Active', '!=', 'FALSE');
        
        if (!empty($explicitAccountId)) {
            $matched = $allAccounts->firstWhere('Account_ID', $explicitAccountId) ?? $allAccounts->firstWhere('Account_Code', $explicitAccountId);
            if ($matched) {
                return $matched['Account_Code'] ?? $matched['Account_ID'];
            }
            throw new FinancialIntegrityException("Account {$explicitAccountId} tidak ditemukan atau tidak aktif.");
        }

        $assets = $allAccounts->filter(function($acc) {
            $cat = strtoupper($acc['Account_Category'] ?? '');
            return str_contains($cat, 'ASSET') || str_contains($cat, 'ASET');
        });

        $methodUpper = strtoupper(trim($paymentMethod));
        $configuredId = $methodUpper === 'CASH' || $methodUpper === 'TUNAI'
            ? config('finance.accounts.cash_id')
            : config('finance.accounts.bank_id');
        if (!empty($configuredId)) {
            $configured = $assets->first(fn ($acc) => ($acc['Account_ID'] ?? '') === $configuredId || ($acc['Account_Code'] ?? '') === $configuredId);
            if (!$configured) {
                throw new FinancialIntegrityException('Akun pembayaran terkonfigurasi tidak ditemukan atau tidak aktif.');
            }
            return $configured['Account_Code'] ?? $configured['Account_ID'];
        }

        if ($methodUpper === 'CASH' || $methodUpper === 'TUNAI') {
            $cashCandidates = $assets->filter(function($acc) {
                $name = strtolower($acc['Account_Name'] ?? '');
                return str_contains($name, 'kas') || str_contains($name, 'cash');
            });
            if ($cashCandidates->count() === 1) return $cashCandidates->first()['Account_Code'] ?? $cashCandidates->first()['Account_ID'];
        } else {
            $bankCandidates = $assets->filter(function($acc) {
                $name = strtolower($acc['Account_Name'] ?? '');
                return str_contains($name, 'bank') || str_contains($name, 'bsi');
            });
            if ($bankCandidates->count() === 1) return $bankCandidates->first()['Account_Code'] ?? $bankCandidates->first()['Account_ID'];
        }

        throw new FinancialIntegrityException('Tidak ada akun asset aktif yang valid untuk pembayaran ini.');
    }

    public function create(array $data)
    {
        return $this->submitPayment($data);
    }

    public function submitPayment(array $data)
    {
        $invoiceId = trim((string) ($data['Invoice_ID'] ?? ''));
        $selfService = !empty($data['Self_Service'])
            || strcasecmp(trim((string) ($data['Payment_Type'] ?? '')), 'STUDENT_SELF_SERVICE') === 0;
        $actorId = \App\Support\ActorIdentity::required();
        $actorRole = $this->authenticatedRoleName(auth()->user());
        if (!$selfService && !in_array($actorRole, ['STUDENT', 'FINANCE', 'ADMINISTRATOR', 'MASTER'], true)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Role pengguna tidak diizinkan membuat pembayaran.');
        }
        if ($selfService) {
            $user = auth()->user();
            if (!$user || $this->authenticatedRoleName($user) !== 'STUDENT') {
                throw new \Illuminate\Auth\Access\AuthorizationException('Self-service payment hanya dapat dibuat oleh siswa.');
            }
            $student = collect($this->studentRepository->fetchAll())->firstWhere('User_ID', $user->User_ID);
            if (!$student || empty($student['Student_ID'])) {
                throw new \Illuminate\Auth\Access\AuthorizationException('Identitas siswa tidak dapat dipastikan.');
            }
            foreach (['Payment_ID', 'Transaction_ID', 'Receipt_Number', 'Reference_Type', 'Reference_ID', 'Account_ID', 'Invoice_ID'] as $forbidden) {
                if (array_key_exists($forbidden, $data) && trim((string) ($data[$forbidden] ?? '')) !== '') {
                    throw new FinancialIntegrityException("Field {$forbidden} tidak boleh ditentukan oleh siswa.");
                }
            }
            if (array_key_exists('Student_ID', $data)
                && trim((string) ($data['Student_ID'] ?? '')) !== ''
                && trim((string) $data['Student_ID']) !== trim((string) $student['Student_ID'])) {
                throw new FinancialIntegrityException('Student_ID pembayaran mandiri tidak sesuai dengan identitas akun.');
            }
            $data['Student_ID'] = $student['Student_ID'];
            $data['Payment_Type'] = 'STUDENT_SELF_SERVICE';
            $data['Invoice_ID'] = '';
        } elseif ($invoiceId === '') {
            throw new Exception("ID Tagihan (Invoice_ID) wajib diisi.");
        }
        $idempotencyKey = trim((string) ($data['Idempotency_Key'] ?? request()->header('Idempotency-Key', '')));
        if ($idempotencyKey === '') {
            // Legacy staff forms may not yet carry a token. Student forms are
            // given a UUID by StudentBillingController; this fallback keeps
            // older internal callers functional while preventing blank scope.
            $idempotencyKey = (string) Str::uuid();
        }
        if (!Str::isUuid($idempotencyKey)) {
            throw new FinancialIntegrityException('Idempotency_Key pembayaran harus berupa UUID.');
        }
        $data['Idempotency_Key'] = $idempotencyKey;
        $fingerprintData = $data;
        unset($fingerprintData['Idempotency_Key']);
        unset($fingerprintData['Created_At'], $fingerprintData['Payment_ID']);
        $fingerprint = hash('sha256', json_encode($fingerprintData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $idempotencyCacheKey = 'payment_idempotency_' . hash('sha256', $actorId . ':' . $idempotencyKey);

        $lockKey = $selfService
            ? 'payment_submit_self_service_' . hash('sha256', $actorId . ':' . $idempotencyKey)
            : "payment_submit_{$invoiceId}";
        return Cache::lock($lockKey, 120)->block(15, function () use ($data, $invoiceId, $actorId, $idempotencyCacheKey, $fingerprint, $idempotencyKey, $selfService) {
            // Cache is only an optimisation.  The persisted row is the source
            // of truth, so a stale/poisoned cache entry can never manufacture
            // a successful duplicate response or override a durable conflict.
            // A cache pointer is only a hint.  Always perform the complete
            // fresh collection lookup below so duplicate persisted identities
            // are detected even when the cache points at one of them.
            $invoiceService = app(InvoiceService::class);
            $preloadedInvoice = null;
            $student = null;
            // For a student-linked payment, establish ownership before any
            // payment collection read.  This preserves the IDOR boundary even
            // when the caller has supplied a duplicate idempotency token.
            if (!$selfService && $this->authenticatedRoleName(auth()->user()) === 'STUDENT') {
                $preloadedInvoice = $this->freshInvoice($invoiceId, $invoiceService);
                if (!$preloadedInvoice || ($preloadedInvoice['Is_Active'] ?? 'TRUE') === 'FALSE') {
                    throw new Exception("Tagihan #{$invoiceId} tidak ditemukan atau sedang tidak aktif.");
                }
                $student = collect($this->studentRepository->fetchAll())->firstWhere('User_ID', auth()->user()->User_ID);
                if (!$student || ($preloadedInvoice['Student_ID'] ?? '') !== ($student['Student_ID'] ?? '')) {
                    throw new Exception("Akses Ditolak: Tagihan #{$invoiceId} bukan milik akun Anda.");
                }
            }
            $allPayments = method_exists($this->paymentRepository, 'getAllFresh')
                ? $this->paymentRepository->getAllFresh()
                : $this->paymentRepository->getAll();
            $persistedByKey = collect($allPayments)
                ->filter(fn ($row) => trim((string) ($row['Idempotency_Key'] ?? '')) === $idempotencyKey)
                ->values();
            if ($persistedByKey->count() > 1) {
                throw new FinancialIntegrityException('Idempotency key pembayaran memiliki lebih dari satu record tersimpan.');
            }
            if ($persistedByKey->isNotEmpty()) {
                $persisted = (array) $persistedByKey->first();
                $this->assertPersistedIdempotency($persisted, $idempotencyKey, $fingerprint, $actorId, $data);
                if ($selfService) {
                    Log::notice('finance.student_payment_duplicate_prevented', [
                        'student_id' => (string) ($data['Student_ID'] ?? 'UNKNOWN'),
                        'payment_id' => (string) ($persisted['Payment_ID'] ?? 'UNKNOWN'),
                    ]);
                }
                return $persisted;
            }
            $invoice = $selfService ? null : ($preloadedInvoice ?? $this->freshInvoice($invoiceId, $invoiceService));
        if (!$selfService && (!$invoice || ($invoice['Is_Active'] ?? 'TRUE') === 'FALSE')) {
            throw new Exception("Tagihan #{$invoiceId} tidak ditemukan atau sedang tidak aktif.");
        }

        // 1. IDOR Ownership Verification for STUDENT role
        $user = auth()->user();
        if ($selfService) {
            // Student identity was resolved above from the authenticated user.
        } elseif ($user && $this->authenticatedRoleName($user) === 'STUDENT') {
            $student = $student ?? collect($this->studentRepository->fetchAll())->firstWhere('User_ID', $user->User_ID);
            if (!$student || ($invoice['Student_ID'] ?? '') !== $student['Student_ID']) {
                throw new Exception("Akses Ditolak: Tagihan #{$invoiceId} bukan milik akun Anda.");
            }
            $data['Student_ID'] = $student['Student_ID'];
        } else {
            $data['Student_ID'] = $data['Student_ID'] ?? $invoice['Student_ID'] ?? null;
            $data['Company_ID'] = $data['Company_ID'] ?? $invoice['Company_ID'] ?? null;
        }
        if (!$selfService && ($invoice['Invoice_Type'] ?? 'STUDENT') === 'STUDENT'
            && !empty($invoice['Student_ID'])
            && ($data['Student_ID'] ?? '') !== $invoice['Student_ID']) {
            throw new FinancialIntegrityException("Student {$data['Student_ID']} bukan pemilik invoice {$invoiceId}.");
        }
        if (!$selfService && ($invoice['Invoice_Type'] ?? '') === 'COMPANY'
            && !empty($invoice['Company_ID'])
            && ($data['Company_ID'] ?? '') !== $invoice['Company_ID']) {
            throw new FinancialIntegrityException("Company {$data['Company_ID']} tidak cocok dengan invoice {$invoiceId}.");
        }

        // 2. Invoice State Protection
        $invoiceStatus = $invoice['Status'] ?? 'Draft';
        if ($selfService) {
            $invoiceStatus = 'SELF_SERVICE';
        }
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
        $remainingAmount = $selfService ? null : (method_exists($this->paymentRepository, 'getAllFresh')
            ? max(0.0, round(
                Money::value($invoice['Amount'] ?? 0, 'Invoice Amount')
                - collect($allPayments)->where('Invoice_ID', $invoiceId)
                    ->filter(fn ($item) => PaymentStatus::verified($item['Status'] ?? null))
                    ->sum(fn ($item) => Money::value($item['Amount_Paid'] ?? 0, 'Nominal pembayaran')),
                Money::SCALE
            ))
            : $invoiceService->calculateRemainingAmount($invoice));
        if (!$selfService && $remainingAmount <= 0) {
            throw new Exception("Tagihan #{$invoiceId} tidak memiliki sisa piutang (Sisa Rp 0).");
        }

        $amountPaid = Money::value($data['Amount_Paid'] ?? 0, 'Nominal pembayaran', false);
        if (!$selfService && $amountPaid > $remainingAmount) {
            throw new Exception("Nominal pembayaran (Rp " . number_format($amountPaid, 0, ',', '.') . ") melebihi sisa tagihan (Rp " . number_format($remainingAmount, 0, ',', '.') . ").");
        }

        // Assign default receipt & metadata
        $data['Payment_Type'] = $data['Payment_Type'] ?? ($selfService ? 'STUDENT_SELF_SERVICE' : ($invoice['Invoice_Type'] ?? 'STUDENT'));
        $data['Payment_Method'] = $data['Payment_Method'] ?? 'TRANSFER';
        $data['Idempotency_Fingerprint'] = $fingerprint;
        if (empty($data['Payment_ID'])) {
            if (empty($data['Receipt_Number'])) {
                $data['Receipt_Number'] = $this->generateReceiptNumber($data['Payment_Type']);
            }
            // Deterministic business identity survives cache loss and makes an
            // HTTP retry converge on the same primary key.
            $data['Payment_ID'] = 'PAY-' . strtoupper(substr(hash('sha256', $actorId . ':' . $idempotencyKey), 0, 24));
        } elseif (empty($data['Receipt_Number'])) {
            $data['Receipt_Number'] = $data['Payment_ID'];
        }
        
        $data['Status'] = 'Waiting Verification';
        $data['Payment_Date'] = $data['Payment_Date'] ?? $data['Transfer_Date'] ?? now()->toDateString();
        try {
            $data['Payment_Date'] = \Carbon\Carbon::parse($data['Payment_Date'])->timezone(config('app.timezone', 'Asia/Jakarta'))->toDateString();
        } catch (\Throwable $e) {
            throw new FinancialIntegrityException('Tanggal pembayaran tidak valid.');
        }
        $data['Amount_Paid'] = $amountPaid;
        $data['Created_At'] = now()->toDateTimeString();
        $data['Created_By'] = $actorId;
        $data['Updated_By'] = $actorId;
        $data['Is_Active'] = 'TRUE';

        Cache::put($idempotencyCacheKey, ['status' => 'processing', 'payment_id' => $data['Payment_ID'], 'fingerprint' => $fingerprint], now()->addHours(2));

        try {
            $res = $this->paymentRepository->create($data);
        } catch (DuplicatePrimaryKeyException $e) {
            $persisted = $this->freshPayment($data['Payment_ID']);
            if (!$persisted || !$this->samePaymentBusinessPayload($persisted, $data)) {
                throw new FinancialIntegrityException('Payment_ID idempotent bertabrakan dengan payload pembayaran berbeda.', 0, $e);
            }
            $res = $persisted;
        }
        if ($res === false || $res === null) {
            throw new Exception("Gagal menyimpan pembayaran {$data['Payment_ID']}.");
        }
        // Never report success from the append response alone.  A fresh read
        // must prove the identity fields survived persistence (especially
        // Payment_Type for self-service and both idempotency columns).
        $persisted = $this->freshPayment($data['Payment_ID']);
        if (!$persisted) {
            throw new FinancialIntegrityException("Payment {$data['Payment_ID']} tersimpan tetapi belum dapat dibaca ulang secara authoritative.");
        }
        $this->assertPersistedIdempotency($persisted, $idempotencyKey, $fingerprint, $actorId, $data);
        if ($selfService && !$this->isSelfServicePayment($persisted)) {
            throw new FinancialIntegrityException('Payment_Type self-service tidak bertahan setelah persistence.');
        }
        $resultData = $persisted;
        $this->paymentRepository->clearCache();
        
        if (!empty($data['Student_ID'])) {
            Cache::forget("student_billing_{$data['Student_ID']}");
        }
        Cache::forget('finance_dashboard');
        Cache::forget('dashboard_finance');

        try {
            $this->enterpriseEvent->dispatch(
                'FINANCE', 'CREATE', 'PAYMENT', $data['Payment_ID'], $actorId,
                ['FINANCE'], !empty($data['Student_ID']) ? [$data['Student_ID']] : [],
                array_intersect_key($data, array_flip(['Invoice_ID', 'Student_ID', 'Amount_Paid', 'Payment_Date', 'Payment_Type']))
            );
        } catch (\Throwable $e) {
            Log::error('Payment side effect dispatch failed after primary persistence', [
                'payment_id' => $data['Payment_ID'], 'invoice_id' => $invoiceId,
                'exception' => get_class($e),
            ]);
        }

        Cache::put($idempotencyCacheKey, ['status' => 'completed', 'payment_id' => $data['Payment_ID'], 'fingerprint' => $fingerprint, 'data' => $resultData], now()->addHours(24));
        
            return $resultData;
        });
    }

    public function verifyPayment($paymentId, $verifiedBy, $status, $notes = '', $explicitAccountId = null)
    {
        $this->assertFinanceMutationActor();
        // Read only to discover the invoice lock scope. The authoritative read
        // is repeated inside the invoice-scoped critical section below.
        $initialPayment = $this->freshPayment($paymentId);
        if (!$initialPayment) {
            throw new Exception("Payment #{$paymentId} tidak ditemukan.");
        }
        $invoiceId = trim((string) ($initialPayment['Invoice_ID'] ?? ''));
        $selfService = $this->isSelfServicePayment($initialPayment);
        if ($invoiceId === '' && !$selfService) {
            throw new FinancialIntegrityException("Pembayaran #{$paymentId} tidak memiliki Invoice_ID yang valid.");
        }
        $lockKey = $invoiceId !== '' ? "payment_verify_invoice_{$invoiceId}" : "payment_verify_payment_{$paymentId}";
        
        try {
            return Cache::lock($lockKey, 120)->block(15, function () use ($paymentId, $verifiedBy, $status, $notes, $explicitAccountId, $initialPayment) {
            if (!in_array($status, ['Verified', 'Rejected', 'Need Revision'], true)) {
                throw new Exception("Status verifikasi pembayaran tidak valid.");
            }

            $payment = method_exists($this->paymentRepository, 'getByIdFresh')
                ? $this->freshPayment($paymentId)
                : $initialPayment;
            if (!$payment) {
                throw new Exception("Payment #{$paymentId} tidak ditemukan.");
            }

            $currentStatus = PaymentStatus::canonical($payment['Status'] ?? 'Waiting Verification');
            // Verification is a one-way domain transition. Only payments that
            // are awaiting review (or were sent back for revision) may enter
            // the verification workflow; terminal states cannot be reopened.
            if (in_array($currentStatus, ['Verified', 'Rejected', 'Cancelled', 'Reversed'], true)) {
                $message = match ($currentStatus) {
                    'Verified' => "Pembayaran #{$paymentId} sudah terverifikasi dan tidak dapat diverifikasi ulang.",
                    'Rejected' => "Pembayaran #{$paymentId} sudah ditolak. Silakan minta pembayaran baru dari siswa.",
                    default => "Pembayaran #{$paymentId} berstatus {$currentStatus} dan tidak dapat diverifikasi.",
                };
                throw new FinancialIntegrityException($message);
            }
            if (!in_array($currentStatus, ['Waiting Verification', 'Need Revision'], true)) {
                throw new FinancialIntegrityException("Pembayaran #{$paymentId} tidak dapat diverifikasi dari status {$currentStatus}.");
            }

            $actorId = \App\Support\ActorIdentity::required();

            if ($status === 'Verified') {
                $invoiceId = trim((string) ($payment['Invoice_ID'] ?? ''));
                if (empty($invoiceId) && !$this->isSelfServicePayment($payment)) {
                    throw new Exception("Pembayaran #{$paymentId} tidak memiliki Invoice_ID yang valid.");
                }

                if ($this->isSelfServicePayment($payment)) {
                    // Self-service submissions have no official invoice yet.
                    // They remain unposted until this Finance verification step.
                    $invoice = null;
                } else {

                $invoiceService = app(InvoiceService::class);
                $invoice = $this->freshInvoice($invoiceId, $invoiceService);
                if (!$invoice) {
                    throw new Exception("Tagihan #{$invoiceId} tidak ditemukan.");
                }
                if (($invoice['Invoice_Type'] ?? 'STUDENT') === 'STUDENT'
                    && !empty($invoice['Student_ID'])
                    && ($payment['Student_ID'] ?? '') !== $invoice['Student_ID']) {
                    throw new FinancialIntegrityException("Payment #{$paymentId} tidak dimiliki oleh student invoice {$invoiceId}.");
                }

                $invoiceStatus = trim((string) ($invoice['Status'] ?? 'Draft'));
                if (in_array(strtolower($invoiceStatus), ['draft', 'cancelled'], true)) {
                    throw new Exception("Tagihan #{$invoiceId} berstatus {$invoiceStatus} dan tidak dapat menerima verifikasi pembayaran.");
                }

                $invoiceAmount = Money::value($invoice['Amount'] ?? 0, 'Invoice Amount');
                $paymentAmount = Money::value($payment['Amount_Paid'] ?? 0, 'Nominal pembayaran', false);
                $allFreshPayments = method_exists($this->paymentRepository, 'getAllFresh')
                    ? $this->paymentRepository->getAllFresh()
                    : $this->paymentRepository->getAll();
                $verifiedBefore = (float) collect($allFreshPayments)
                    ->where('Invoice_ID', $invoiceId)
                    ->filter(fn ($item) => PaymentStatus::verified($item['Status'] ?? null))
                    ->reject(fn ($item) => ($item['Payment_ID'] ?? '') === $paymentId)
                    ->sum(fn ($item) => Money::value($item['Amount_Paid'] ?? 0, 'Nominal pembayaran'));

                if (Money::cents($verifiedBefore) + Money::cents($paymentAmount) > Money::cents($invoiceAmount)) {
                    $remaining = max(0.0, round($invoiceAmount - $verifiedBefore, Money::SCALE));
                    throw new Exception(
                        "Verifikasi pembayaran #{$paymentId} ditolak karena nominal Rp "
                        . number_format($paymentAmount, 0, ',', '.')
                        . " melebihi sisa tagihan Rp "
                        . number_format($remaining, 0, ',', '.')
                        . "."
                    );
                }
                }
            }

            $data = [
                'Status' => $status,
                'Verified_By' => $actorId,
                'Verified_At' => now()->toDateTimeString(),
                'Notes' => $notes,
                'Updated_By' => $actorId,
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
                try {
                    $this->reconcileVerifiedPaymentLedger($paymentId, $explicitAccountId);
                } catch (\Throwable $ledgerException) {
                    // Google Sheets does not provide a cross-sheet transaction.
                    // Never leave a payment visibly Verified when its required
                    // income ledger could not be confirmed. Roll the payment
                    // back to its prior review state and verify that rollback
                    // persisted; otherwise fail loudly for manual recovery.
                    try {
                        $rolledBack = $this->paymentRepository->update($paymentId, [
                            'Status' => $currentStatus,
                            'Verified_By' => '',
                            'Verified_At' => '',
                            'Updated_By' => $actorId,
                            'Updated_At' => now()->toDateTimeString(),
                        ]);
                        if (!$rolledBack) {
                            throw new FinancialIntegrityException('Rollback status pembayaran gagal disimpan.');
                        }
                        $this->paymentRepository->clearCache();
                        $persistedRollback = $this->freshPayment($paymentId);
                        if (!$persistedRollback || PaymentStatus::canonical($persistedRollback['Status'] ?? null) !== $currentStatus) {
                            throw new FinancialIntegrityException('Rollback status pembayaran tidak dapat dikonfirmasi.');
                        }
                    } catch (\Throwable $rollbackException) {
                        Log::critical('finance.payment_verification_ledger_gap_requires_manual_recovery', [
                            'payment_id' => $paymentId,
                            'prior_status' => $currentStatus,
                            'ledger_exception' => get_class($ledgerException),
                            'rollback_exception' => get_class($rollbackException),
                        ]);
                        throw new FinancialIntegrityException(
                            "Verifikasi pembayaran #{$paymentId} gagal dan rollback tidak dapat dikonfirmasi.",
                            0,
                            $rollbackException
                        );
                    }

                    throw $ledgerException;
                }

                try {
                    $this->enterpriseEvent->dispatch(
                        'FINANCE', 'VERIFY', 'PAYMENT', $paymentId, $actorId,
                        ['STUDENT'], !empty($payment['Student_ID']) ? [$payment['Student_ID']] : [],
                        ['Status' => $status, 'Notes' => $notes]
                    );
                } catch (\Throwable $e) {
                    Log::error('Payment verification side effect failed after persistence', [
                        'payment_id' => $paymentId, 'exception' => get_class($e),
                    ]);
                }
            } elseif (in_array($status, ['Rejected', 'Need Revision'])) {
                try {
                    $this->enterpriseEvent->dispatch(
                        'FINANCE', 'UPDATE', 'PAYMENT', $paymentId, $actorId,
                        ['STUDENT'], !empty($payment['Student_ID']) ? [$payment['Student_ID']] : [],
                        ['Status' => $status, 'Notes' => $notes]
                    );
                } catch (\Throwable $e) {
                    Log::error('Payment status side effect failed after persistence', [
                        'payment_id' => $paymentId, 'exception' => get_class($e),
                    ]);
                }
            }

            return $res;
            });
        } catch (\Throwable $e) {
            Log::warning('Payment verification lock or critical section failed safely', [
                'payment_id' => $paymentId,
                'invoice_id' => $invoiceId,
                'status' => $status,
                'exception' => get_class($e),
            ]);
            throw $e;
        }
    }

    protected function getVerifiedPaymentTotalForInvoice(string $invoiceId): float
    {
        $all = method_exists($this->paymentRepository, 'getAllFresh')
            ? $this->paymentRepository->getAllFresh()
            : $this->paymentRepository->getAll();
        return (float) collect($all)
            ->where('Invoice_ID', $invoiceId)
            ->filter(fn ($item) => PaymentStatus::verified($item['Status'] ?? null))
            ->sum(fn ($item) => Money::value($item['Amount_Paid'] ?? 0, 'Nominal pembayaran'));
    }

    private function freshPayment(string $paymentId): ?array
    {
        $payment = method_exists($this->paymentRepository, 'getByIdFresh')
            ? $this->paymentRepository->getByIdFresh($paymentId)
            : $this->paymentRepository->getById($paymentId);
        return $payment ? (array) $payment : null;
    }

    private function freshInvoice(string $invoiceId, InvoiceService $invoiceService): ?array
    {
        if (method_exists($this->invoiceRepository, 'findByIdFresh')) {
            $invoice = $this->invoiceRepository->findByIdFresh($invoiceId);
            return $invoice ? (array) $invoice : null;
        }
        return ($invoice = $invoiceService->getById($invoiceId)) ? (array) $invoice : null;
    }

    private function samePaymentBusinessPayload(array $persisted, array $candidate): bool
    {
        foreach (['Invoice_ID', 'Student_ID', 'Company_ID', 'Payment_Type', 'Payment_Method', 'Payment_Date'] as $field) {
            // Before server defaults are applied, an idempotent retry may not
            // include optional fields such as Payment_Date or Method.  Only
            // compare values the caller actually supplied; the persisted
            // fingerprint remains the complete canonical payload check.
            if (!array_key_exists($field, $candidate)) {
                continue;
            }
            if (trim((string) ($persisted[$field] ?? '')) !== trim((string) ($candidate[$field] ?? ''))) {
                return false;
            }
        }
        return Money::equal($persisted['Amount_Paid'] ?? null, $candidate['Amount_Paid'] ?? null);
    }

    private function assertPersistedIdempotency(array $persisted, string $idempotencyKey, string $fingerprint, string $actorId, array $candidate): void
    {
        $storedKey = trim((string) ($persisted['Idempotency_Key'] ?? ''));
        $storedFingerprint = trim((string) ($persisted['Idempotency_Fingerprint'] ?? ''));
        if ($storedKey === '' || $storedFingerprint === '') {
            throw new FinancialIntegrityException('Payment tersimpan tidak memiliki identitas idempotency durable.');
        }
        if ($storedKey !== $idempotencyKey || !hash_equals($storedFingerprint, $fingerprint)) {
            throw new FinancialIntegrityException('Idempotency key sudah digunakan untuk payload pembayaran yang berbeda.');
        }
        $createdBy = trim((string) ($persisted['Created_By'] ?? ''));
        if ($createdBy === '' || !hash_equals($createdBy, $actorId)) {
            throw new FinancialIntegrityException('Idempotency identity pembayaran dimiliki actor lain.');
        }
        $paymentId = trim((string) ($persisted['Payment_ID'] ?? ''));
        $expectedPaymentId = 'PAY-' . strtoupper(substr(hash('sha256', $actorId . ':' . $idempotencyKey), 0, 24));
        if ($paymentId === '' || !hash_equals($paymentId, $expectedPaymentId)) {
            throw new FinancialIntegrityException('Payment_ID deterministic tidak konsisten dengan idempotency identity.');
        }
        if (!$this->samePaymentBusinessPayload($persisted, $candidate)) {
            throw new FinancialIntegrityException('Payment identity tersimpan tidak konsisten dengan payload pembayaran.');
        }
    }

    /**
     * Repair/reconcile the authoritative ledger for an already Verified payment.
     * This operation is deliberately idempotent and always uses fresh reads.
     */
    public function reconcileVerifiedPaymentLedger(string $paymentId, ?string $explicitAccountId = null): array
    {
        $actor = $this->assertFinanceMutationActor();
        Log::notice('finance.payment_ledger_repair_attempted', [
            'payment_id' => $paymentId, 'actor' => $actor,
        ]);

        try {
            $payment = $this->freshPayment($paymentId);
            if (!$payment || strtoupper(trim((string) ($payment['Is_Active'] ?? 'TRUE'))) === 'FALSE') {
                throw new FinancialIntegrityException("Payment #{$paymentId} tidak ditemukan atau tidak aktif.");
            }
            if (!PaymentStatus::verified($payment['Status'] ?? null)) {
                throw new FinancialIntegrityException("Payment #{$paymentId} harus berstatus Verified untuk rekonsiliasi ledger.");
            }
            $invoiceId = trim((string) ($payment['Invoice_ID'] ?? ''));
            $selfService = $this->isSelfServicePayment($payment);
            if ($invoiceId === '' && !$selfService) {
                throw new FinancialIntegrityException("Payment #{$paymentId} tidak memiliki Invoice_ID yang valid.");
            }
            $invoice = $selfService ? null : $this->freshInvoice($invoiceId, app(InvoiceService::class));
            if (!$selfService && (!$invoice || strtoupper(trim((string) ($invoice['Is_Active'] ?? 'TRUE'))) === 'FALSE')) {
                throw new FinancialIntegrityException("Invoice #{$invoiceId} tidak ditemukan atau tidak aktif.");
            }
            $amount = Money::value($payment['Amount_Paid'] ?? null, 'Nominal pembayaran', false);
            $expectedAccount = $this->resolvePaymentAccount($payment['Payment_Method'] ?? 'TRANSFER', $explicitAccountId);
            $transactions = collect(method_exists($this->transactionRepository, 'fetchAllFresh')
                ? $this->transactionRepository->fetchAllFresh()
                : $this->transactionRepository->fetchAll());
            $deterministicId = $this->paymentLedgerTransactionId($paymentId);
            $identityCollision = $transactions->firstWhere('Transaction_ID', $deterministicId);
            if ($identityCollision && (
                strcasecmp(trim((string) ($identityCollision['Reference_Type'] ?? '')), 'Payment') !== 0
                || trim((string) ($identityCollision['Reference_ID'] ?? '')) !== $paymentId
            )) {
                throw new FinancialIntegrityException("Deterministic ledger identity untuk Payment #{$paymentId} telah digunakan oleh reference lain.");
            }
            $conflictingReference = $transactions->first(fn ($row) =>
                trim((string) ($row['Reference_ID'] ?? '')) === $paymentId
                && !in_array(strtolower(trim((string) ($row['Reference_Type'] ?? ''))), ['payment', 'paymentreversal'], true)
            );
            if ($conflictingReference) {
                throw new FinancialIntegrityException("Reference ledger untuk Payment #{$paymentId} tidak konsisten.");
            }
            $income = $transactions->first(fn ($row) =>
                strtoupper(trim((string) ($row['Is_Active'] ?? 'TRUE'))) !== 'FALSE'
                && strcasecmp(trim((string) ($row['Reference_Type'] ?? '')), 'Payment') === 0
                && trim((string) ($row['Reference_ID'] ?? '')) === $paymentId
            );
            if ($income) {
                if (strcasecmp(trim((string) ($income['Type'] ?? '')), 'Income') !== 0
                    || !Money::equal($income['Amount'] ?? null, $amount)
                    || trim((string) ($income['Account_ID'] ?? '')) !== trim((string) $expectedAccount)) {
                    throw new FinancialIntegrityException("Income ledger untuk Payment #{$paymentId} tidak konsisten; rekonsiliasi dihentikan.");
                }
            } else {
                $description = $selfService
                    ? "Pembayaran mandiri terverifikasi #{$paymentId}"
                    : "Pembayaran Verifikasi Kuitansi #{$paymentId} untuk Invoice #{$invoiceId}";
                ($this->transactionService ?? app(TransactionService::class))->create([
                    'Transaction_ID' => $deterministicId,
                    'Transaction_Date' => $payment['Payment_Date'] ?? $payment['Transfer_Date'] ?? now()->timezone(config('app.timezone', 'Asia/Jakarta'))->format('Y-m-d'),
                    'Account_ID' => $expectedAccount,
                    'Type' => 'Income',
                    'Category' => 'Payment Receipt',
                    'Amount' => $amount,
                    'Reference_Type' => 'Payment',
                    'Reference_ID' => $paymentId,
                    'Description' => $description,
                ]);
            }

            if (!$selfService) {
                $verified = $this->getVerifiedPaymentTotalForInvoice($invoiceId);
                $invoiceStatus = Money::cents($verified) >= Money::cents(Money::value($invoice['Amount'] ?? 0, 'Invoice Amount'))
                    ? 'Paid' : 'Partial Paid';
                if (!$this->invoiceRepository->update($invoiceId, [
                    'Status' => $invoiceStatus,
                    'Updated_By' => $actor,
                    'Updated_At' => now()->toDateTimeString(),
                ])) {
                    throw new FinancialIntegrityException("Gagal merekonsiliasi status Invoice #{$invoiceId}.");
                }
                $this->invoiceRepository->clearCache();
            }
            Log::notice('finance.payment_ledger_repair_succeeded', [
                'payment_id' => $paymentId, 'invoice_id' => $invoiceId, 'actor' => $actor,
            ]);
            return $this->freshPayment($paymentId) ?? $payment;
        } catch (\Throwable $e) {
            Log::warning('finance.payment_ledger_repair_failed', [
                'payment_id' => $paymentId, 'actor' => $actor,
                'reason' => get_class($e),
            ]);
            throw $e;
        }
    }

    public function deletePayment($paymentId)
    {
        $this->assertFinanceMutationActor();
        $payment = $this->freshPayment($paymentId);
        if (!$payment) {
            throw new Exception("Payment not found");
        }

        $state = PaymentStatus::canonical($payment['Status'] ?? null);
        if (!in_array($state, ['Waiting Verification', 'Need Revision'], true)) {
            throw new FinancialIntegrityException("Pembayaran #{$paymentId} berstatus {$payment['Status']} dan tidak dapat dihapus atau dibatalkan.");
        }

        // Re-read immediately before the destructive mutation so a stale cache
        // or concurrent verification cannot bypass the state machine.
        $payment = $this->freshPayment($paymentId);
        $state = PaymentStatus::canonical($payment['Status'] ?? null);
        if (!in_array($state, ['Waiting Verification', 'Need Revision'], true)) {
            throw new FinancialIntegrityException("Pembayaran #{$paymentId} berubah status dan tidak dapat dihapus atau dibatalkan.");
        }

        $deleted = $this->paymentRepository->update($paymentId, [
            'Status' => 'Cancelled',
            'Is_Active' => 'FALSE',
            'Updated_By' => \App\Support\ActorIdentity::required(),
            'Updated_At' => now()->toDateTimeString(),
        ]);
        if ($deleted === false || $deleted === null) {
            throw new Exception("Gagal menghapus pembayaran #{$paymentId}.");
        }
        $this->paymentRepository->clearCache();
        
        if (!empty($payment['Student_ID'])) {
            Cache::forget("student_billing_{$payment['Student_ID']}");
        }
        Cache::forget('finance_dashboard');
        Cache::forget('dashboard_finance');

        try {
            $this->enterpriseEvent->dispatch(
                'FINANCE', 'UPDATE', 'PAYMENT', $paymentId,
                \App\Support\ActorIdentity::required(), ['FINANCE'],
                !empty($payment['Student_ID']) ? [$payment['Student_ID']] : [],
                ['Status' => 'Cancelled']
            );
        } catch (\Throwable $e) {
            Log::error('Payment cancellation side effect failed after persistence', [
                'payment_id' => $paymentId, 'exception' => get_class($e),
            ]);
        }
        
        return true;
    }

    /**
     * Non-destructive correction for a verified payment. The original income
     * transaction remains intact and an equal expense transaction compensates
     * it. A retry reuses the deterministic PaymentReversal reference.
     */
    public function reversePayment(string $paymentId, string $reason, ?string $explicitAccountId = null): array
    {
        return $this->reconcilePaymentReversal($paymentId, $reason, $explicitAccountId);
    }

    /**
     * Deterministically creates or repairs the reversal ledger for a payment.
     * This is safe to retry after an ambiguous append or invoice update failure.
     */
    public function reconcilePaymentReversal(string $paymentId, ?string $reason = null, ?string $explicitAccountId = null): array
    {
        $actor = $this->assertFinanceMutationActor();
        $reason = trim((string) ($reason ?? 'Recovery reversal'));
        if ($reason === '') {
            throw new FinancialIntegrityException('Alasan reversal wajib diisi.');
        }
        $initial = $this->freshPayment($paymentId);
        if (!$initial) {
            throw new FinancialIntegrityException("Payment #{$paymentId} tidak ditemukan.");
        }
        $invoiceId = trim((string) ($initial['Invoice_ID'] ?? ''));
        $lockScope = $invoiceId !== '' ? "invoice_{$invoiceId}" : "payment_{$paymentId}";

        Log::notice('finance.payment_reversal_repair_attempted', ['payment_id' => $paymentId, 'actor' => $actor]);
        try {
            return Cache::lock("payment_verify_{$lockScope}", 120)->block(15, function () use ($paymentId, $reason, $explicitAccountId, $actor) {
                $payment = $this->freshPayment($paymentId);
                if (!$payment) {
                    throw new FinancialIntegrityException("Payment #{$paymentId} tidak ditemukan.");
                }
                $state = PaymentStatus::canonical($payment['Status'] ?? null);
                if (!in_array($state, ['Verified', 'Reversed'], true)) {
                    throw new FinancialIntegrityException("Payment #{$paymentId} harus berstatus Verified atau Reversed untuk recovery reversal.");
                }

                $transactions = collect(method_exists($this->transactionRepository, 'fetchAllFresh')
                    ? $this->transactionRepository->fetchAllFresh()
                    : $this->transactionRepository->fetchAll());
                $original = $transactions->first(fn ($row) =>
                    strtoupper(trim((string) ($row['Is_Active'] ?? 'TRUE'))) !== 'FALSE'
                    && strcasecmp(trim((string) ($row['Reference_Type'] ?? '')), 'Payment') === 0
                    && trim((string) ($row['Reference_ID'] ?? '')) === $paymentId
                );
                if (!$original || strcasecmp(trim((string) ($original['Type'] ?? '')), 'Income') !== 0
                    || !Money::equal($original['Amount'] ?? null, $payment['Amount_Paid'] ?? null)
                    || trim((string) ($original['Reference_ID'] ?? '')) !== $paymentId) {
                    throw new FinancialIntegrityException("Income ledger asli untuk payment #{$paymentId} tidak valid.");
                }
                $accountId = trim((string) ($original['Account_ID'] ?? ''));
                if ($explicitAccountId !== null && trim((string) $explicitAccountId) !== $accountId) {
                    throw new FinancialIntegrityException('Reversal harus menggunakan account yang sama dengan income transaction asli.');
                }
                if ($accountId === '') {
                    throw new FinancialIntegrityException("Account transaksi asli untuk payment #{$paymentId} tidak ditemukan.");
                }

                $deterministicId = $this->paymentReversalTransactionId($paymentId);
                $idCollision = $transactions->firstWhere('Transaction_ID', $deterministicId);
                if ($idCollision && (strcasecmp(trim((string) ($idCollision['Reference_Type'] ?? '')), 'PaymentReversal') !== 0
                    || trim((string) ($idCollision['Reference_ID'] ?? '')) !== $paymentId)) {
                    throw new FinancialIntegrityException("Deterministic reversal identity untuk Payment #{$paymentId} telah digunakan oleh reference lain.");
                }
                $reversal = $transactions->first(fn ($row) =>
                    strtoupper(trim((string) ($row['Is_Active'] ?? 'TRUE'))) !== 'FALSE'
                    && strcasecmp(trim((string) ($row['Reference_Type'] ?? '')), 'PaymentReversal') === 0
                    && trim((string) ($row['Reference_ID'] ?? '')) === $paymentId
                );
                if ($reversal) {
                    if (trim((string) ($reversal['Transaction_ID'] ?? '')) !== $deterministicId
                        || strcasecmp(trim((string) ($reversal['Type'] ?? '')), 'Expense') !== 0
                        || !Money::equal($reversal['Amount'] ?? null, $payment['Amount_Paid'] ?? null)
                        || trim((string) ($reversal['Account_ID'] ?? '')) !== $accountId) {
                        throw new FinancialIntegrityException("Reversal ledger untuk Payment #{$paymentId} tidak konsisten.");
                    }
                } else {
                    try {
                        ($this->transactionService ?? app(TransactionService::class))->create([
                            'Transaction_ID' => $deterministicId,
                            'Transaction_Date' => now()->timezone(config('app.timezone', 'Asia/Jakarta'))->toDateString(),
                            'Account_ID' => $accountId,
                            'Type' => 'Expense',
                            'Category' => 'Payment Reversal',
                            'Amount' => Money::value($payment['Amount_Paid'] ?? 0, 'Nominal reversal', false),
                            'Reference_Type' => 'PaymentReversal',
                            'Reference_ID' => $paymentId,
                            'Description' => 'Reversal payment #' . $paymentId . ': ' . $reason,
                            '_domain_reversal' => true,
                        ]);
                    } catch (\Throwable $e) {
                        $fresh = collect(method_exists($this->transactionRepository, 'fetchAllFresh')
                            ? $this->transactionRepository->fetchAllFresh()
                            : $this->transactionRepository->fetchAll())
                            ->first(fn ($row) => trim((string) ($row['Transaction_ID'] ?? '')) === $deterministicId);
                        if (!$fresh) {
                            throw $e;
                        }
                        if (strcasecmp(trim((string) ($fresh['Reference_Type'] ?? '')), 'PaymentReversal') !== 0
                            || trim((string) ($fresh['Reference_ID'] ?? '')) !== $paymentId
                            || strcasecmp(trim((string) ($fresh['Type'] ?? '')), 'Expense') !== 0
                            || !Money::equal($fresh['Amount'] ?? null, $payment['Amount_Paid'] ?? null)
                            || trim((string) ($fresh['Account_ID'] ?? '')) !== $accountId) {
                            throw new FinancialIntegrityException("Ambiguous reversal append menghasilkan ledger yang tidak konsisten.", 0, $e);
                        }
                    }
                }

                if ($state === 'Verified') {
                    if (!$this->paymentRepository->update($paymentId, [
                        'Status' => 'Reversed', 'Notes' => $reason,
                        'Updated_By' => $actor, 'Updated_At' => now()->toDateTimeString(),
                    ])) {
                        throw new FinancialIntegrityException("Reversal payment #{$paymentId} belum dapat dikonfirmasi.");
                    }
                    $this->paymentRepository->clearCache();
                }
                $invoiceId = trim((string) ($payment['Invoice_ID'] ?? ''));
                if ($invoiceId !== '') {
                    $this->reconcileInvoiceStatus($invoiceId);
                }
                $result = $this->freshPayment($paymentId) ?? array_merge($payment, ['Status' => 'Reversed']);
                Log::notice('finance.payment_reversal_repair_succeeded', ['payment_id' => $paymentId, 'actor' => $actor]);
                return $result;
            });
        } catch (\Throwable $e) {
            Log::warning('finance.payment_reversal_repair_failed', [
                'payment_id' => $paymentId, 'actor' => $actor, 'reason' => get_class($e),
            ]);
            throw $e;
        }
    }

    private function assertFinanceMutationActor(): string
    {
        $actor = \App\Support\ActorIdentity::required();
        $role = $this->authenticatedRoleName(auth()->user());
        if (!in_array($role, ['FINANCE', 'ADMINISTRATOR', 'MASTER'], true)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Role pengguna tidak diizinkan melakukan mutasi keuangan.');
        }
        return $actor;
    }

    /**
     * Resolve the authoritative role name from the authenticated SSOT user.
     * Tests may provide Role directly, while production sessions normally
     * carry Role_ID and require the role repository lookup.
     */
    private function authenticatedRoleName($user): string
    {
        if (!$user) {
            return '';
        }
        $role = trim((string) ($user->Role ?? $user->Role_Name ?? ''));
        if ($role !== '') {
            return strtoupper($role);
        }
        $roleId = trim((string) ($user->Role_ID ?? ''));
        if ($roleId === '') {
            return '';
        }
        try {
            $roleRow = app(\App\Services\Core\RoleService::class)->getRoleById($roleId);
            return strtoupper(trim((string) ($roleRow['Role_Name'] ?? '')));
        } catch (\Throwable) {
            return '';
        }
    }

    private function paymentLedgerTransactionId(string $paymentId): string
    {
        return 'TRX-PAY-' . strtoupper(substr(hash('sha256', $paymentId), 0, 20));
    }

    private function paymentReversalTransactionId(string $paymentId): string
    {
        return 'TRX-REV-' . strtoupper(substr(hash('sha256', $paymentId), 0, 20));
    }

    private function isSelfServicePayment(array $payment): bool
    {
        return strcasecmp(trim((string) ($payment['Payment_Type'] ?? '')), 'STUDENT_SELF_SERVICE') === 0
            || !empty($payment['Self_Service']);
    }

    private function reconcileInvoiceStatus(string $invoiceId): void
    {
        $invoiceService = app(InvoiceService::class);
        $invoice = $this->freshInvoice($invoiceId, $invoiceService);
        if (!$invoice) {
            throw new FinancialIntegrityException("Invoice #{$invoiceId} tidak ditemukan saat rekonsiliasi.");
        }
        $verified = $this->getVerifiedPaymentTotalForInvoice($invoiceId);
        $amount = Money::value($invoice['Amount'] ?? 0, 'Invoice Amount');
        $status = Money::cents($verified) >= Money::cents($amount)
            ? 'Paid'
            : (Money::cents($verified) > 0 ? 'Partial Paid' : 'Waiting Payment');
        if (!$this->invoiceRepository->update($invoiceId, [
            'Status' => $status,
            'Updated_By' => \App\Support\ActorIdentity::required(),
            'Updated_At' => now()->toDateTimeString(),
        ])) {
            throw new FinancialIntegrityException("Status invoice #{$invoiceId} gagal direkonsiliasi.");
        }
        $this->invoiceRepository->clearCache();
    }
}
