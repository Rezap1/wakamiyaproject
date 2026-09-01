<?php

namespace App\Services\Finance;

use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;
use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;
use App\Exceptions\DuplicatePrimaryKeyException;
use App\Support\ActorIdentity;
use Throwable;
use App\Support\Finance\Money;
use App\Support\Finance\PaymentStatus;
use App\Exceptions\FinancialIntegrityException;

class InvoiceService
{
    protected $repository;
    protected $enterpriseEvent;
    protected $studentRepository;
    protected $companyRepository;
    protected $paymentRepository;

    public function __construct(
        InvoiceRepositoryInterface $repository, 
        EnterpriseEventService $enterpriseEvent,
        StudentRepositoryInterface $studentRepository,
        CompanyRepositoryInterface $companyRepository,
        PaymentRepositoryInterface $paymentRepository
    ) {
        $this->repository = $repository;
        $this->enterpriseEvent = $enterpriseEvent;
        $this->studentRepository = $studentRepository;
        $this->companyRepository = $companyRepository;
        $this->paymentRepository = $paymentRepository;
    }

    public function getVerifiedPaymentTotal(string $invoiceId): float
    {
        if (empty($invoiceId)) return 0.0;
        
        $allPayments = method_exists($this->paymentRepository, 'getAllFresh')
            ? $this->paymentRepository->getAllFresh()
            : $this->paymentRepository->getAll();
        return (float) collect($allPayments)
            ->where('Invoice_ID', $invoiceId)
            ->filter(fn ($payment) => PaymentStatus::verified($payment['Status'] ?? null))
            ->sum(fn ($payment) => Money::value($payment['Amount_Paid'] ?? 0, 'Nominal pembayaran'));
    }

    public function calculateRemainingAmount(array $invoice): float
    {
        $amount = Money::value($invoice['Amount'] ?? 0, 'Invoice Amount');
        $paid = $this->getVerifiedPaymentTotal($invoice['Invoice_ID'] ?? '');
        return max(0.0, round($amount - Money::value($paid, 'Verified payment total'), Money::SCALE));
    }

    public function resolveDynamicStatus(array $invoice): string
    {
        $currentStatus = trim($invoice['Status'] ?? 'Draft');

        if (strcasecmp($currentStatus, 'Cancelled') === 0) {
            return 'Cancelled';
        }

        if (strcasecmp($currentStatus, 'Draft') === 0) {
            return 'Draft';
        }

        $remaining = $this->calculateRemainingAmount($invoice);
        
        if ($remaining <= 0) {
            return 'Paid';
        }

        $dueDateStr = $invoice['Due_Date'] ?? null;
        $isOverdue = false;
        if ($dueDateStr) {
            try {
                $isOverdue = Carbon::parse($dueDateStr)->startOfDay()->lt(now()->startOfDay());
            } catch (\Exception $e) {
                $isOverdue = false;
            }
        }

        if ($isOverdue && $remaining > 0) {
            return 'OVERDUE';
        }

        $paid = $this->getVerifiedPaymentTotal($invoice['Invoice_ID'] ?? '');
        
        if ($paid > 0 && $remaining > 0) {
            return 'Partial Paid';
        }

        return 'Waiting Payment';
    }

    public function formatInvoiceRecord(array $invoice): array
    {
        // Process Line Items
        $lineItems = [];
        if (!empty($invoice['Line_Items'])) {
            if (is_array($invoice['Line_Items'])) {
                $lineItems = $invoice['Line_Items'];
            } else {
                try {
                    $decoded = json_decode($invoice['Line_Items'], true);
                    if (is_array($decoded)) {
                        $lineItems = $decoded;
                    } else {
                        throw new FinancialIntegrityException("Invoice {$invoice['Invoice_ID']} memiliki Line_Items tidak valid.");
                    }
                } catch (FinancialIntegrityException $e) {
                    throw $e;
                } catch (\Throwable $e) {
                    throw new FinancialIntegrityException("Invoice {$invoice['Invoice_ID']} memiliki Line_Items tidak valid.", 0, $e);
                }
            }
        }

        // Fallback for legacy invoices without line items
        if (empty($lineItems)) {
            $baseAmount = Money::value($invoice['Amount'] ?? 0, 'Invoice Amount');
            $lineItems = [
                [
                    'description' => $invoice['Category'] ?? 'Tagihan Keuangan',
                    'qty' => 1,
                    'unit_price' => $baseAmount,
                    'discount' => 0,
                    'tax' => 0,
                    'subtotal' => $baseAmount
                ]
            ];
        }

        [$lineItems, $subtotal, $totalDiscount, $totalTax, $grandTotal] = $this->calculateLineItemsTotal($lineItems, $invoice['Amount'] ?? 0);

        if (array_key_exists('Amount', $invoice) && !Money::equal($invoice['Amount'], $grandTotal)) {
            throw new FinancialIntegrityException(
                "Invoice {$invoice['Invoice_ID']} memiliki Amount yang tidak sama dengan total line item."
            );
        }

        // Resolve status and balances only after canonical Amount is known.
        $invoice['Amount'] = $grandTotal;
        $dynamicStatus = $this->resolveDynamicStatus($invoice);
        $paidAmount = $this->getVerifiedPaymentTotal($invoice['Invoice_ID'] ?? '');
        $remainingAmount = max(0.0, round($grandTotal - Money::value($paidAmount, 'Verified payment total'), Money::SCALE));

        $invoice['Parsed_Line_Items'] = $lineItems;
        $invoice['Subtotal_Amount'] = $subtotal;
        $invoice['Total_Discount'] = $totalDiscount;
        $invoice['Total_Tax'] = $totalTax;
        $invoice['Grand_Total'] = $grandTotal;

        $invoice['Display_Status'] = $dynamicStatus;
        $invoice['Status'] = $dynamicStatus;
        $invoice['Paid_Amount'] = $paidAmount;
        $invoice['Remaining_Amount'] = $remainingAmount;
        $invoice['Is_Overdue'] = ($dynamicStatus === 'OVERDUE');

        return $invoice;
    }

    /**
     * Canonical invoice calculation shared by create, display and reporting.
     * Returns normalised items and all component totals.
     */
    public function calculateLineItemsTotal(array|string|null $items, mixed $legacyAmount = 0): array
    {
        if (is_string($items)) {
            $decoded = json_decode($items, true);
            $items = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($items) || $items === []) {
            $amount = Money::value($legacyAmount, 'Invoice Amount');
            $items = [[
                'description' => 'Tagihan Keuangan',
                'qty' => 1,
                'unit_price' => $amount,
                'discount' => 0,
                'tax' => 0,
            ]];
        }

        $subtotal = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;
        $grandTotal = 0.0;
        $normalised = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new FinancialIntegrityException('Line item invoice tidak valid.');
            }
            $qty = Money::value($item['qty'] ?? 1, 'Item quantity', false);
            $price = Money::value($item['unit_price'] ?? 0, 'Item unit price');
            $discount = Money::value($item['discount'] ?? 0, 'Item discount');
            $tax = Money::value($item['tax'] ?? 0, 'Item tax');
            $base = round($qty * $price, Money::SCALE);
            $itemTotal = round(max(0.0, $base - $discount + $tax), Money::SCALE);
            $subtotal = round($subtotal + $base, Money::SCALE);
            $discountTotal = round($discountTotal + $discount, Money::SCALE);
            $taxTotal = round($taxTotal + $tax, Money::SCALE);
            $grandTotal = round($grandTotal + $itemTotal, Money::SCALE);
            $normalised[] = [
                'description' => (string) ($item['description'] ?? 'Item Tagihan'),
                'qty' => $qty,
                'unit_price' => $price,
                'discount' => $discount,
                'tax' => $tax,
                'subtotal' => $itemTotal,
            ];
        }

        return [$normalised, $subtotal, $discountTotal, $taxTotal, $grandTotal];
    }

    public function isEducationCategory(?string $category): bool
    {
        $category = strtolower(trim((string) $category));

        return $category !== ''
            && (str_contains($category, 'pendidikan')
                || str_contains($category, 'spp')
                || str_contains($category, 'tuition'));
    }

    public function isEducationInvoice(array $invoice): bool
    {
        if ($this->isEducationCategory($invoice['Category'] ?? '')) {
            return true;
        }

        if ($this->isEducationCategory($invoice['Description'] ?? '')) {
            return true;
        }

        $lineItems = $invoice['Line_Items'] ?? [];
        if (is_string($lineItems) && trim($lineItems) !== '') {
            $decodedItems = json_decode($lineItems, true);
            if (!is_array($decodedItems)) {
                throw new FinancialIntegrityException("Invoice {$invoice['Invoice_ID']} memiliki Line_Items tidak valid.");
            }
            $lineItems = $decodedItems;
        }

        if (!is_array($lineItems)) {
            return false;
        }

        foreach ($lineItems as $item) {
            if (is_array($item) && $this->isEducationCategory($item['description'] ?? '')) {
                return true;
            }
        }

        return false;
    }

    public function getStudentTuitionFee(string $studentId): float
    {
        $settingService = app(\App\Services\Core\SystemSettingService::class);
        $tuitionFee = (float) $settingService->getDefaultTuitionFee();
        $student = $this->studentRepository->findById($studentId);

        if ($student) {
            if (!empty($student['Program_ID'])) {
                $program = app(\App\Interfaces\GoogleSheets\ProgramRepositoryInterface::class)->findById($student['Program_ID']);
                if ($program && !empty($program['Tuition_Fee']) && is_numeric($program['Tuition_Fee'])) {
                    $tuitionFee = (float) $program['Tuition_Fee'];
                }
            }

            if (!empty($student['Batch_ID'])) {
                $batch = app(\App\Interfaces\GoogleSheets\BatchRepositoryInterface::class)->findById($student['Batch_ID']);
                if ($batch && !empty($batch['Tuition_Fee']) && is_numeric($batch['Tuition_Fee'])) {
                    $tuitionFee = (float) $batch['Tuition_Fee'];
                }
            }
        }

        return max(0.0, $tuitionFee);
    }

    public function getStudentEducationBillingSummary(string $studentId, ?string $excludeInvoiceId = null): array
    {
        $tuitionFee = $this->getStudentTuitionFee($studentId);
        $educationInvoices = collect(method_exists($this->repository, 'getAllFresh')
            ? $this->repository->getAllFresh()
            : $this->repository->getAll())->filter(function ($invoice) use ($studentId, $excludeInvoiceId) {
            $status = strtolower(trim($invoice['Status'] ?? ''));

            return ($invoice['Student_ID'] ?? '') === $studentId
                && ($invoice['Is_Active'] ?? 'TRUE') !== 'FALSE'
                && in_array($status, ['waiting payment', 'partial paid', 'paid', 'overdue'], true)
                && ($excludeInvoiceId === null || ($invoice['Invoice_ID'] ?? '') !== $excludeInvoiceId)
                && $this->isEducationInvoice($invoice);
        });

        $invoiceIds = $educationInvoices->pluck('Invoice_ID')->filter()->values()->all();
        $billedCents = $educationInvoices->sum(fn($invoice) => Money::cents($this->rawInvoiceAmount($invoice), 'Invoice Amount'));
        $paidCents = collect(method_exists($this->paymentRepository, 'getAllFresh')
            ? $this->paymentRepository->getAllFresh()
            : $this->paymentRepository->getAll())
            ->whereIn('Invoice_ID', $invoiceIds)
            ->filter(fn ($payment) => PaymentStatus::verified($payment['Status'] ?? null))
            ->sum(fn($payment) => Money::cents($payment['Amount_Paid'] ?? 0, 'Nominal pembayaran'));
        $billed = $billedCents / 100;
        $paid = $paidCents / 100;

        return [
            'tuition_fee' => $tuitionFee,
            'education_billed' => $billed,
            'education_paid' => $paid,
            'remaining_to_bill' => max(0.0, $tuitionFee - $billed),
            'remaining_to_pay' => max(0.0, $tuitionFee - $paid),
            'progress' => $tuitionFee > 0 ? min(100, round(($paid / $tuitionFee) * 100)) : 0,
        ];
    }

    private function rawInvoiceAmount(array $invoice): float
    {
        $items = $invoice['Line_Items'] ?? null;
        if ($items !== null && $items !== '' && $items !== []) {
            [, , , , $total] = $this->calculateLineItemsTotal($items, $invoice['Amount'] ?? 0);
            if (array_key_exists('Amount', $invoice) && !Money::equal($invoice['Amount'], $total)) {
                throw new FinancialIntegrityException("Invoice {$invoice['Invoice_ID']} memiliki Amount yang tidak sama dengan total line item.");
            }
            return $total;
        }
        return Money::value($invoice['Amount'] ?? 0, 'Invoice Amount');
    }

    private function enforceEducationTuitionCap(array $data, ?string $excludeInvoiceId = null): void
    {
        if (($data['Invoice_Type'] ?? 'STUDENT') !== 'STUDENT'
            || empty($data['Student_ID'])
            || !$this->isEducationCategory($data['Category'] ?? '')) {
            return;
        }

        $newAmount = Money::value($data['Amount'] ?? 0, 'Invoice Amount');
        $summary = $this->getStudentEducationBillingSummary($data['Student_ID'], $excludeInvoiceId);
        $tuitionFee = (float) $summary['tuition_fee'];
        $alreadyBilled = (float) $summary['education_billed'];

        if ($tuitionFee <= 0) {
            return;
        }

        if (Money::cents($alreadyBilled) + Money::cents($newAmount) > Money::cents($tuitionFee)) {
            $remaining = max(0.0, $tuitionFee - $alreadyBilled);
            throw new Exception(
                'Total invoice Biaya Pendidikan siswa ini tidak boleh melebihi plafon Rp '
                . number_format($tuitionFee, 0, ',', '.')
                . '. Sudah ditagihkan Rp ' . number_format($alreadyBilled, 0, ',', '.')
                . ', sisa yang boleh dibuat Rp ' . number_format($remaining, 0, ',', '.')
                . '.'
            );
        }
    }

    private function assertInvoiceReferences(array $data): void
    {
        $type = strtoupper(trim((string) ($data['Invoice_Type'] ?? 'STUDENT')));
        if ($type === 'STUDENT') {
            $id = trim((string) ($data['Student_ID'] ?? ''));
            $student = $id !== '' ? $this->studentRepository->findById($id) : null;
            if (!$student || strtoupper(trim((string) ($student['Is_Active'] ?? 'TRUE'))) === 'FALSE') {
                throw new FinancialIntegrityException("Student {$id} tidak ditemukan atau tidak aktif.");
            }
        } elseif ($type === 'COMPANY') {
            $id = trim((string) ($data['Company_ID'] ?? ''));
            $company = $id !== '' ? $this->companyRepository->findById($id) : null;
            if (!$company || strtoupper(trim((string) ($company['Is_Active'] ?? 'TRUE'))) === 'FALSE') {
                throw new FinancialIntegrityException("Company {$id} tidak ditemukan atau tidak aktif.");
            }
        }
    }

    public function getAll() 
    { 
        $invoices = collect($this->repository->getAll())->where('Is_Active', '!=', 'FALSE')->values();
        $user = auth()->user();
        
        // Role values originate from legacy sheets and are not guaranteed to be
        // upper-case. Keep the student data boundary fail-closed regardless of
        // presentation casing.
        if ($user && strtoupper(trim((string) ($user->Role ?? ''))) === 'STUDENT') {
            $student = collect($this->studentRepository->fetchAll())->firstWhere('User_ID', $user->User_ID);
            if ($student) {
                $invoices = $invoices->where('Student_ID', $student['Student_ID'])->values();
            } else {
                return collect();
            }
        }

        return $invoices->map(function($inv) {
            return $this->formatInvoiceRecord($inv);
        });
    }

    public function getById($id) { 
        $invoice = $this->repository->getById($id); 
        if ($invoice) {
            return $this->formatInvoiceRecord($invoice);
        }
        return null;
    }

    public function getInvoiceDocumentData(string $invoiceId, bool $allowPublicVerification = false): array
    {
        $invoice = $this->getById($invoiceId);
        if (!$invoice) {
            throw new Exception("Tagihan (Invoice) #{$invoiceId} tidak ditemukan.");
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
                if (!$student || ($invoice['Student_ID'] ?? '') !== ($student['Student_ID'] ?? '')) {
                    throw new Exception("Akses Ditolak: Tagihan #{$invoiceId} bukan milik akun Anda.");
                }
            } elseif (!in_array($role, ['MASTER', 'ADMINISTRATOR', 'FINANCE'], true)) {
                throw new Exception("Akses Ditolak: Role pengguna tidak diizinkan mengakses tagihan.");
            }
        }

        // Customer Lookup
        $customerName = '-';
        $customerCode = '-';
        $customerType = $invoice['Invoice_Type'] ?? 'STUDENT';

        if ($customerType === 'STUDENT' && !empty($invoice['Student_ID'])) {
            $student = $this->studentRepository->findById($invoice['Student_ID']);
            $customerName = $student['Full_Name'] ?? $invoice['Student_ID'];
            $customerCode = $student['Student_Number'] ?? $invoice['Student_ID'];
        } elseif ($customerType === 'COMPANY' && !empty($invoice['Company_ID'])) {
            $company = $this->companyRepository->findById($invoice['Company_ID']);
            $customerName = $company['Company_Name'] ?? $invoice['Company_ID'];
            $customerCode = $company['Company_Code'] ?? $invoice['Company_ID'];
        } else {
            $customerName = $invoice['Student_ID'] ?? $invoice['Company_ID'] ?? 'Pelanggan';
            $customerCode = $invoice['Student_ID'] ?? $invoice['Company_ID'] ?? '-';
        }

        // QR Verification URL
        $verificationUrl = \App\Helpers\PublicVerificationUrl::make('invoices.verify-public', $invoiceId);

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
            'invoice' => $invoice,
            'customer' => [
                'type' => $customerType,
                'name' => $customerName,
                'code' => $customerCode
            ],
            'verificationUrl' => $verificationUrl,
            'qrCodeSvg' => $qrCodeSvg
        ];
    }

    public function generateInvoiceNumber($type = 'STUDENT')
    {
        $prefix = strtoupper((string) $type) === 'COMPANY' ? 'INV-CORP' : 'INV-STU';
        $year = (int) date('Y');

        return Cache::lock('invoice_creation_lock', 120)->block(15,
            fn () => $this->allocateInvoiceNumberLocked($prefix, $year)
        );
    }

    private function allocateInvoiceNumberLocked(string $prefix, int $year): string
    {
        $counterKey = "invoice_counter_{$prefix}_{$year}";
        $this->repository->clearCache();
        $rows = method_exists($this->repository, 'getAllFresh')
            ? $this->repository->getAllFresh()
            : $this->repository->getAll();
        $maxSuffix = 0;
        $pattern = '/^' . preg_quote($prefix, '/') . '-' . $year . '-(\\d+)$/i';

        foreach ($rows as $row) {
            $id = trim((string) ($row['Invoice_ID'] ?? ''));
            if (preg_match($pattern, $id, $matches) === 1) {
                $maxSuffix = max($maxSuffix, (int) $matches[1]);
            }
        }

        // Cache is an optimization only. A stale/lower value is reconciled to
        // the persisted maximum, while a higher value remains reserved.
        $cachedSuffix = max(0, (int) Cache::get($counterKey, 0));
        $candidate = max($maxSuffix, $cachedSuffix) + 1;
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $invoiceId = sprintf('%s-%d-%06d', $prefix, $year, $candidate);
            $exists = collect($rows)->contains(function ($row) use ($invoiceId) {
                return strcasecmp(trim((string) ($row['Invoice_ID'] ?? '')), $invoiceId) === 0;
            });
            if (!$exists) {
                Cache::forever($counterKey, $candidate);
                Log::info('Invoice number allocated', [
                    'invoice_id' => $invoiceId,
                    'prefix' => $prefix,
                    'year' => $year,
                    'max_persisted_suffix' => $maxSuffix,
                    'cached_suffix' => $cachedSuffix,
                    'attempt' => $attempt,
                ]);
                return $invoiceId;
            }
            $candidate++;
        }

        throw new Exception("Tidak dapat mengalokasikan nomor invoice aman untuk {$prefix}-{$year}.");
    }

    public function create(array $data)
    {
        $this->assertFinanceMutationActor();
        $data['Invoice_Type'] = $data['Invoice_Type'] ?? 'STUDENT';
        $providedInvoiceId = trim((string) ($data['Invoice_ID'] ?? ''));
        $idempotencyKey = trim((string) ($data['Idempotency_Key'] ?? ''));
        unset($data['Idempotency_Key']);
        $actorId = ActorIdentity::required();
        $requestId = request()->header('X-Request-ID') ?: (string) Str::uuid();

        // Canonical amount is calculated once and persisted with the line items.
        [$processedItems, $subtotal, $discountTotal, $taxTotal, $grandTotal] =
            $this->calculateLineItemsTotal($data['items'] ?? null, $data['Amount'] ?? 0);
        $data['Line_Items'] = json_encode($processedItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $data['Amount'] = $grandTotal;

        $this->assertInvoiceReferences($data);

        if ($this->isEducationCategory($data['Category'] ?? '')) {
            $data['Category'] = 'Biaya Pendidikan';
        }

        $fingerprint = hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $run = function () use (&$data, $idempotencyKey, $fingerprint, $actorId, $requestId, $providedInvoiceId) {
            $idempotencyCacheKey = $idempotencyKey !== ''
                ? 'invoice_idempotency_' . hash('sha256', $actorId . ':' . $idempotencyKey)
                : null;
            if ($idempotencyCacheKey) {
                $existing = Cache::get($idempotencyCacheKey);
                if ($existing && ($existing['fingerprint'] ?? '') !== $fingerprint) {
                    throw new Exception('Idempotency key sudah digunakan untuk payload invoice yang berbeda.');
                }
                if (($existing['status'] ?? null) === 'completed' && !empty($existing['data'])) {
                    return $existing['data'];
                }
                if (($existing['status'] ?? null) === 'processing' && !empty($existing['invoice_id'])) {
                    $persisted = method_exists($this->repository, 'findByIdFresh')
                        ? $this->repository->findByIdFresh($existing['invoice_id'])
                        : $this->repository->getById($existing['invoice_id']);
                    if ($persisted) {
                        $data = array_merge($data, $persisted);
                        return $this->completeInvoiceCreation($data, $idempotencyCacheKey, $fingerprint, $actorId, $requestId, false);
                    }
                    $data['Invoice_ID'] = $existing['invoice_id'];
                }
            }

            $write = function () use (&$data, $idempotencyCacheKey, $idempotencyKey, $fingerprint, $actorId, $requestId, $providedInvoiceId) {
                if ($idempotencyCacheKey && empty($data['Invoice_ID'])) {
                    // The record is populated immediately after allocation so a
                    // retry can verify the same Invoice_ID instead of generating
                    // another invoice blindly.
                    Cache::put($idempotencyCacheKey, [
                        'status' => 'processing',
                        'invoice_id' => null,
                        'fingerprint' => $fingerprint,
                    ], now()->addHours(2));
                }

                for ($attempt = 1; $attempt <= 5; $attempt++) {
                    if (empty($data['Invoice_ID'])) {
                        $prefix = strtoupper((string) $data['Invoice_Type']) === 'COMPANY' ? 'INV-CORP' : 'INV-STU';
                        $data['Invoice_ID'] = $this->allocateInvoiceNumberLocked($prefix, (int) date('Y'));
                    }
                    if ($idempotencyCacheKey) {
                        Cache::put($idempotencyCacheKey, [
                            'status' => 'processing',
                            'invoice_id' => $data['Invoice_ID'],
                            'fingerprint' => $fingerprint,
                        ], now()->addHours(2));
                    }

                    $this->enforceEducationTuitionCap($data);
                    $data['Status'] = 'Draft';
                    $data['Created_At'] = $data['Created_At'] ?? now()->toDateTimeString();
                    $data['Created_By'] = $data['Created_By'] ?? $actorId;
                    $data['Updated_By'] = $actorId;
                    $data['Is_Active'] = 'TRUE';

                    try {
                        $result = $this->repository->create($data);
                        if (!$result) {
                            throw new Exception("Gagal menyimpan invoice {$data['Invoice_ID']}.");
                        }

                        // The append response is not authoritative.  Google
                        // Sheets may accept a row while silently dropping a
                        // field when headers drift.  Concrete production
                        // repositories expose findByIdFresh(); verify the
                        // canonical line items and amount from that fresh row
                        // before reporting invoice creation success.
                        if (method_exists($this->repository, 'findByIdFresh')) {
                            $persisted = $this->repository->findByIdFresh($data['Invoice_ID']);
                            $this->assertPersistedInvoiceIntegrity($persisted, $data);
                            $data = array_merge($data, (array) $persisted);
                        }
                        return $data;
                    } catch (DuplicatePrimaryKeyException $e) {
                        if ($attempt >= 5 || $providedInvoiceId !== '') {
                            throw $e;
                        }
                        $data['Invoice_ID'] = null;
                        Log::warning('Invoice number collision reconciled', [
                            'request_id' => $requestId,
                            'idempotency_key' => $idempotencyKey !== '' ? hash('sha256', $idempotencyKey) : null,
                            'attempt' => $attempt,
                            'exception' => get_class($e),
                        ]);
                    }
                }
        throw new Exception('Pembuatan invoice melebihi batas percobaan aman.');
    };

            $executeWrite = function () use ($write, &$data) {
                if ($this->isEducationCategory($data['Category'] ?? '') && !empty($data['Student_ID'])) {
                    $lockKey = 'invoice_tuition_' . sha1((string) $data['Student_ID']);
                    return Cache::lock($lockKey, 120)->block(15, $write);
                }
                return $write();
            };

            $persisted = Cache::lock('invoice_creation_lock', 120)->block(15, $executeWrite);
            return $this->completeInvoiceCreation($persisted, $idempotencyCacheKey, $fingerprint, $actorId, $requestId);
        };

        if ($idempotencyKey === '') {
            return $run();
        }

        $lockKey = 'invoice_idempotency_lock_' . hash('sha256', $actorId . ':' . $idempotencyKey);
        return Cache::lock($lockKey, 120)->block(15, $run);
    }

    /**
     * Verify correctness-critical invoice fields after the datastore append.
     * This deliberately does not synthesize missing values: a missing or
     * malformed Line_Items field is a schema/data-integrity failure.
     */
    private function assertPersistedInvoiceIntegrity($persisted, array $expected): void
    {
        if (!is_array($persisted) || trim((string) ($persisted['Invoice_ID'] ?? '')) !== trim((string) ($expected['Invoice_ID'] ?? ''))) {
            throw new FinancialIntegrityException('Invoice tersimpan tetapi tidak dapat dibaca ulang secara authoritative.');
        }

        if (!array_key_exists('Line_Items', $persisted)) {
            throw new FinancialIntegrityException('Line_Items invoice hilang setelah persistence.');
        }

        $rawItems = $persisted['Line_Items'];
        if (is_string($rawItems)) {
            $decoded = json_decode($rawItems, true);
            if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
                throw new FinancialIntegrityException('Line_Items invoice tersimpan dalam format tidak valid.');
            }
            $rawItems = $decoded;
        }
        if (!is_array($rawItems)) {
            throw new FinancialIntegrityException('Line_Items invoice tersimpan dalam format tidak valid.');
        }

        $expectedItems = $expected['Line_Items'] ?? null;
        if (is_string($expectedItems)) {
            $expectedItems = json_decode($expectedItems, true);
        }
        if (!is_array($expectedItems) || $rawItems !== $expectedItems) {
            throw new FinancialIntegrityException('Line_Items invoice berubah atau tidak konsisten setelah persistence.');
        }

        if (!Money::equal($persisted['Amount'] ?? null, $expected['Amount'] ?? null)) {
            throw new FinancialIntegrityException('Amount invoice berubah atau tidak konsisten setelah persistence.');
        }
    }

    private function completeInvoiceCreation(array $data, ?string $idempotencyCacheKey, string $fingerprint, string $actorId, string $requestId, bool $dispatchSideEffects = true): array
    {
        try {
            $this->repository->clearCache();
            if (!empty($data['Student_ID'])) {
                Cache::forget("student_billing_{$data['Student_ID']}");
            }
            Cache::forget('finance_dashboard');
            Cache::forget('dashboard_finance');
        } catch (Throwable $e) {
            Log::warning('Invoice cache invalidation failed after primary persistence', [
                'request_id' => $requestId,
                'invoice_id' => $data['Invoice_ID'] ?? null,
                'exception' => get_class($e),
            ]);
        }

        if ($dispatchSideEffects) {
            try {
                $eventMetadata = array_intersect_key($data, array_flip([
                    'Invoice_Type', 'Category', 'Due_Date', 'Student_ID', 'Company_ID'
                ]));
                $this->enterpriseEvent->dispatch(
                    'FINANCE', 'CREATE', 'INVOICE', $data['Invoice_ID'], $actorId,
                    ['FINANCE'], !empty($data['Student_ID']) ? [$data['Student_ID']] : [], $eventMetadata
                );
            } catch (Throwable $e) {
                Log::error('Invoice side effect dispatch failed after primary persistence', [
                    'request_id' => $requestId,
                    'invoice_id' => $data['Invoice_ID'] ?? null,
                    'exception' => get_class($e),
                ]);
            }
        }

        $result = $this->formatCreatedInvoice($data);
        if ($idempotencyCacheKey) {
            Cache::put($idempotencyCacheKey, [
                'status' => 'completed',
                'invoice_id' => $result['Invoice_ID'],
                'fingerprint' => $fingerprint,
                'data' => $result,
            ], now()->addHours(24));
        }
        Log::info('Invoice primary persistence succeeded', [
            'request_id' => $requestId,
            'invoice_id' => $result['Invoice_ID'] ?? null,
            'actor_id' => $actorId,
            'result' => 'success',
        ]);
        return $result;
    }

    private function formatCreatedInvoice(array $data): array
    {
        $lineItems = $data['Line_Items'] ?? [];
        if (is_string($lineItems)) {
            $decoded = json_decode($lineItems, true);
            $lineItems = is_array($decoded) ? $decoded : [];
        }
        $amount = (float) ($data['Amount'] ?? 0);
        $data['Parsed_Line_Items'] = $lineItems;
        $data['Grand_Total'] = $amount;
        $data['Display_Status'] = 'Draft';
        $data['Status'] = 'Draft';
        $data['Paid_Amount'] = 0.0;
        $data['Remaining_Amount'] = $amount;
        $data['Is_Overdue'] = false;
        return $data;
    }

    public function publish($id)
    {
        $this->assertFinanceMutationActor();
        // Cancellation decisions must use persisted state, not a potentially
        // stale service/cache representation.
        $invoice = method_exists($this->repository, 'findByIdFresh')
            ? $this->repository->findByIdFresh($id)
            : $this->getById($id);
        if (!$invoice) {
            throw new Exception("Invoice tidak ditemukan.");
        }

        $currentStatus = trim($invoice['Status'] ?? 'Draft');
        if (strcasecmp($currentStatus, 'Draft') !== 0) {
            throw new Exception("Hanya invoice berstatus Draft yang dapat diterbitkan.");
        }

        $data = [
            'Status' => 'Waiting Payment',
            'Updated_By' => \App\Support\ActorIdentity::required(),
            'Updated_At' => now()->toDateTimeString()
        ];

        $res = $this->repository->update($id, $data);
        if (!$res) {
            throw new Exception("Gagal menerbitkan invoice {$id}.");
        }
        $this->repository->clearCache();

        if (!empty($invoice['Student_ID'])) {
            Cache::forget("student_billing_{$invoice['Student_ID']}");
        }
        Cache::forget('finance_dashboard');
        Cache::forget('dashboard_finance');

        $this->dispatchEventSafe($id, ['Status' => 'Waiting Payment', 'Previous_Status' => 'Draft'], $invoice, ['STUDENT', 'FINANCE']);

        $updatedInvoice = array_merge($invoice, $data);
        return $this->formatInvoiceRecord($updatedInvoice);
    }

    public function cancel($id)
    {
        $this->assertFinanceMutationActor();
        $invoice = method_exists($this->repository, 'findByIdFresh')
            ? $this->repository->findByIdFresh($id)
            : $this->repository->getById($id);
        if (!$invoice) {
            throw new Exception("Invoice tidak ditemukan.");
        }

        $currentStatus = trim($invoice['Status'] ?? 'Draft');
        if (in_array(strtolower($currentStatus), ['paid', 'cancelled'])) {
            throw new Exception("Invoice yang sudah Lunas (Paid) atau Dibatalkan (Cancelled) tidak dapat dibatalkan lagi.");
        }

        $payments = method_exists($this->paymentRepository, 'getAllFresh')
            ? $this->paymentRepository->getAllFresh()
            : $this->paymentRepository->getAll();
        $verifiedCents = collect($payments)
            ->where('Invoice_ID', $id)
            ->filter(fn ($payment) => PaymentStatus::verified($payment['Status'] ?? null))
            ->sum(fn ($payment) => Money::cents($payment['Amount_Paid'] ?? 0, 'Nominal pembayaran'));
        if ($verifiedCents > 0) {
            throw new FinancialIntegrityException("Invoice #{$id} memiliki pembayaran terverifikasi dan tidak dapat dibatalkan.");
        }

        $data = [
            'Status' => 'Cancelled',
            'Updated_By' => \App\Support\ActorIdentity::required(),
            'Updated_At' => now()->toDateTimeString()
        ];

        $res = $this->repository->update($id, $data);
        if (!$res) {
            throw new Exception("Gagal membatalkan invoice {$id}.");
        }
        $this->repository->clearCache();

        if (!empty($invoice['Student_ID'])) {
            Cache::forget("student_billing_{$invoice['Student_ID']}");
        }
        Cache::forget('finance_dashboard');
        Cache::forget('dashboard_finance');

        $this->dispatchEventSafe($id, ['Status' => 'Cancelled', 'Previous_Status' => $currentStatus], $invoice);

        $updatedInvoice = array_merge($invoice, $data);
        return $this->formatInvoiceRecord($updatedInvoice);
    }

    private function assertFinanceMutationActor(): string
    {
        $actor = ActorIdentity::required();
        $user = auth()->user();
        $role = strtoupper(trim((string) ($user->Role ?? $user->Role_Name ?? '')));
        if ($role === '' && $user && !empty($user->Role_ID)) {
            try {
                $role = strtoupper(trim((string) (app(\App\Services\Core\RoleService::class)
                    ->getRoleById($user->Role_ID)['Role_Name'] ?? '')));
            } catch (Throwable) {
                $role = '';
            }
        }
        if (!in_array($role, ['FINANCE', 'ADMINISTRATOR', 'MASTER'], true)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Role pengguna tidak diizinkan melakukan mutasi invoice.');
        }
        return $actor;
    }

    public function update($id, array $data)
    {
        $this->assertFinanceMutationActor();
        $invoice = method_exists($this->repository, 'findByIdFresh')
            ? $this->repository->findByIdFresh($id)
            : $this->getById($id);
        if (!$invoice) {
            throw new Exception("Invoice tidak ditemukan.");
        }

        $currentStatus = trim($invoice['Status'] ?? 'Draft');
        if (strcasecmp($currentStatus, 'Draft') !== 0) {
            throw new Exception("Hanya invoice berstatus Draft yang dapat diubah nilainya atau rincian itemnya.");
        }

        // Process items with the same canonical calculator used for display.
        if (array_key_exists('items', $data)) {
            [$processedItems, , , , $grandTotal] = $this->calculateLineItemsTotal($data['items'], $data['Amount'] ?? $invoice['Amount'] ?? 0);
            $data['Line_Items'] = json_encode($processedItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $data['Amount'] = $grandTotal;
        }

        if ($this->isEducationCategory($data['Category'] ?? ($invoice['Category'] ?? ''))) {
            $data['Category'] = 'Biaya Pendidikan';
            $data['Student_ID'] = $data['Student_ID'] ?? ($invoice['Student_ID'] ?? '');
            $this->enforceEducationTuitionCap($data, $id);
        }

        $this->assertInvoiceReferences(array_merge($invoice, $data));

        $data['Updated_By'] = \App\Support\ActorIdentity::required();
        $data['Updated_At'] = now()->toDateTimeString();

        $res = $this->repository->update($id, $data);
        if (!$res) {
            throw new Exception("Gagal memperbarui invoice {$id}.");
        }
        $this->repository->clearCache();

        if (!empty($invoice['Student_ID'])) {
            Cache::forget("student_billing_{$invoice['Student_ID']}");
        }
        if (!empty($data['Student_ID']) && ($data['Student_ID'] ?? '') !== ($invoice['Student_ID'] ?? '')) {
            Cache::forget("student_billing_{$data['Student_ID']}");
        }
        Cache::forget('finance_dashboard');
        Cache::forget('dashboard_finance');

        $this->dispatchEventSafe($id, $data, $invoice);

        $updatedInvoice = array_merge($invoice, $data);
        return $this->formatInvoiceRecord($updatedInvoice);
    }

    public function delete($id)
    {
        $this->assertFinanceMutationActor();
        $invoice = method_exists($this->repository, 'findByIdFresh')
            ? $this->repository->findByIdFresh($id)
            : $this->getById($id);
        if (!$invoice) {
            throw new Exception("Invoice tidak ditemukan.");
        }

        $currentStatus = trim($invoice['Status'] ?? 'Draft');
        if (strcasecmp($currentStatus, 'Draft') !== 0) {
            throw new Exception("Hanya invoice berstatus Draft yang dapat dihapus.");
        }

        $res = $this->repository->update($id, [
            'Status' => 'Cancelled',
            'Is_Active' => 'FALSE',
            'Updated_By' => \App\Support\ActorIdentity::required(),
            'Updated_At' => now()->toDateTimeString(),
        ]);
        if (!$res) {
            throw new Exception("Gagal menghapus invoice {$id}.");
        }
        $this->repository->clearCache();

        if (!empty($invoice['Student_ID'])) {
            Cache::forget("student_billing_{$invoice['Student_ID']}");
        }
        Cache::forget('finance_dashboard');
        Cache::forget('dashboard_finance');

        $this->dispatchEventSafe($id, ['Status' => 'Cancelled', 'Previous_Status' => $currentStatus], $invoice);

        return true;
    }

    private function dispatchEventSafe(string $invoiceId, array $metadata, array $invoice, array $targetRoles = ['FINANCE']): void
    {
        try {
            $this->enterpriseEvent->dispatch(
                'FINANCE', 'UPDATE', 'INVOICE', $invoiceId, ActorIdentity::required(),
                $targetRoles, !empty($invoice['Student_ID']) ? [$invoice['Student_ID']] : [], $metadata
            );
        } catch (Throwable $e) {
            Log::error('Invoice side effect failed after primary persistence', [
                'invoice_id' => $invoiceId, 'exception' => get_class($e),
            ]);
        }
    }
}
