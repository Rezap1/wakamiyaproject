<?php

namespace App\Services\Finance;

use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;
use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Exception;

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
        
        $allPayments = $this->paymentRepository->getAll();
        return (float) collect($allPayments)
            ->where('Invoice_ID', $invoiceId)
            ->where('Status', 'Verified')
            ->sum('Amount_Paid');
    }

    public function calculateRemainingAmount(array $invoice): float
    {
        $amount = (float) ($invoice['Amount'] ?? 0);
        $paid = $this->getVerifiedPaymentTotal($invoice['Invoice_ID'] ?? '');
        return max(0.0, $amount - $paid);
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
        $dynamicStatus = $this->resolveDynamicStatus($invoice);
        $paidAmount = $this->getVerifiedPaymentTotal($invoice['Invoice_ID'] ?? '');
        $remainingAmount = $this->calculateRemainingAmount($invoice);

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
                    }
                } catch (\Exception $e) {
                    $lineItems = [];
                }
            }
        }

        // Fallback for legacy invoices without line items
        if (empty($lineItems)) {
            $baseAmount = (float) ($invoice['Amount'] ?? 0);
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

        $subtotal = 0;
        $totalDiscount = 0;
        $totalTax = 0;
        $grandTotal = 0;

        foreach ($lineItems as &$item) {
            $qty = (float) ($item['qty'] ?? 1);
            $price = (float) ($item['unit_price'] ?? 0);
            $disc = (float) ($item['discount'] ?? 0);
            $tax = (float) ($item['tax'] ?? 0);
            $itemSubtotal = max(0.0, ($qty * $price) - $disc + $tax);

            $item['qty'] = $qty;
            $item['unit_price'] = $price;
            $item['discount'] = $disc;
            $item['tax'] = $tax;
            $item['subtotal'] = $itemSubtotal;

            $subtotal += ($qty * $price);
            $totalDiscount += $disc;
            $totalTax += $tax;
            $grandTotal += $itemSubtotal;
        }

        $invoice['Parsed_Line_Items'] = $lineItems;
        $invoice['Subtotal_Amount'] = $subtotal;
        $invoice['Total_Discount'] = $totalDiscount;
        $invoice['Total_Tax'] = $totalTax;
        $invoice['Grand_Total'] = $grandTotal;
        $invoice['Amount'] = $grandTotal > 0 ? $grandTotal : (float)($invoice['Amount'] ?? 0);

        $invoice['Display_Status'] = $dynamicStatus;
        $invoice['Status'] = $dynamicStatus;
        $invoice['Paid_Amount'] = $paidAmount;
        $invoice['Remaining_Amount'] = $remainingAmount;
        $invoice['Is_Overdue'] = ($dynamicStatus === 'OVERDUE');

        return $invoice;
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
            $lineItems = is_array($decodedItems) ? $decodedItems : [];
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
        $educationInvoices = collect($this->repository->getAll())->filter(function ($invoice) use ($studentId, $excludeInvoiceId) {
            $status = strtolower(trim($invoice['Status'] ?? ''));

            return ($invoice['Student_ID'] ?? '') === $studentId
                && ($invoice['Is_Active'] ?? 'TRUE') !== 'FALSE'
                && $status !== 'cancelled'
                && ($excludeInvoiceId === null || ($invoice['Invoice_ID'] ?? '') !== $excludeInvoiceId)
                && $this->isEducationInvoice($invoice);
        });

        $invoiceIds = $educationInvoices->pluck('Invoice_ID')->filter()->values()->all();
        $billed = (float) $educationInvoices->sum(fn($invoice) => $this->rawInvoiceAmount($invoice));
        $paid = (float) collect($this->paymentRepository->getAll())
            ->whereIn('Invoice_ID', $invoiceIds)
            ->where('Status', 'Verified')
            ->sum('Amount_Paid');

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
        if (isset($invoice['Grand_Total']) && is_numeric($invoice['Grand_Total'])) {
            return (float) $invoice['Grand_Total'];
        }

        return (float) ($invoice['Amount'] ?? 0);
    }

    private function enforceEducationTuitionCap(array $data, ?string $excludeInvoiceId = null): void
    {
        if (($data['Invoice_Type'] ?? 'STUDENT') !== 'STUDENT'
            || empty($data['Student_ID'])
            || !$this->isEducationCategory($data['Category'] ?? '')) {
            return;
        }

        $newAmount = (float) ($data['Amount'] ?? 0);
        $summary = $this->getStudentEducationBillingSummary($data['Student_ID'], $excludeInvoiceId);
        $tuitionFee = (float) $summary['tuition_fee'];
        $alreadyBilled = (float) $summary['education_billed'];

        if ($tuitionFee <= 0) {
            return;
        }

        if (($alreadyBilled + $newAmount) > ($tuitionFee + 0.001)) {
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
            } elseif (!in_array($role, ['ADMINISTRATOR', 'FINANCE'], true)) {
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
        $prefix = $type === 'COMPANY' ? 'INV-CORP' : 'INV-STU';
        $year = date('Y');
        
        $counterKey = 'invoice_counter_' . $prefix . '_' . $year;
        $lockKey = 'invoice_write_lock';

        return Cache::lock($lockKey, 10)->block(5, function () use ($prefix, $year, $counterKey) {
            if (!Cache::has($counterKey)) {
                $all = $this->repository->getAll();
                $count = collect($all)->filter(function($item) use ($prefix, $year) {
                    return str_starts_with($item['Invoice_ID'] ?? '', "{$prefix}-{$year}-");
                })->count();
                Cache::forever($counterKey, $count);
            }
            
            $nextNumber = Cache::increment($counterKey);
            return sprintf("%s-%s-%06d", $prefix, $year, $nextNumber);
        });
    }

    public function create(array $data)
    {
        $data['Invoice_Type'] = $data['Invoice_Type'] ?? 'STUDENT';
        
        if (empty($data['Invoice_ID'])) {
            $data['Invoice_ID'] = $this->generateInvoiceNumber($data['Invoice_Type']);
        }

        // Process Items Array
        $items = $data['items'] ?? [];
        $processedItems = [];
        $grandTotal = 0;

        if (!empty($items) && is_array($items)) {
            foreach ($items as $item) {
                $qty = (float)($item['qty'] ?? 1);
                $unitPrice = (float)($item['unit_price'] ?? 0);
                $discount = (float)($item['discount'] ?? 0);
                $tax = (float)($item['tax'] ?? 0);
                $subtotal = max(0.0, ($qty * $unitPrice) - $discount + $tax);
                $grandTotal += $subtotal;
                
                $processedItems[] = [
                    'description' => $item['description'] ?? 'Item Tagihan',
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'discount' => $discount,
                    'tax' => $tax,
                    'subtotal' => $subtotal
                ];
            }
            $data['Line_Items'] = json_encode($processedItems);
            $data['Amount'] = $grandTotal;
        } else {
            $data['Amount'] = (float) ($data['Amount'] ?? 0);
            $data['Line_Items'] = json_encode([
                [
                    'description' => $data['Category'] ?? 'Tagihan Keuangan',
                    'qty' => 1,
                    'unit_price' => $data['Amount'],
                    'discount' => 0,
                    'tax' => 0,
                    'subtotal' => $data['Amount']
                ]
            ]);
        }

        if ($this->isEducationCategory($data['Category'] ?? '')) {
            $data['Category'] = 'Biaya Pendidikan';
        }

        $persist = function () use (&$data) {
            $this->enforceEducationTuitionCap($data);

            $data['Status'] = 'Draft';
            $data['Created_At'] = now()->toDateTimeString();
            $data['Is_Active'] = 'TRUE';

            $result = $this->repository->create($data);
            if (!$result) {
                throw new Exception("Gagal menyimpan invoice {$data['Invoice_ID']}.");
            }

            return $result;
        };

        if ($this->isEducationCategory($data['Category'] ?? '') && !empty($data['Student_ID'])) {
            $lockKey = 'invoice_tuition_' . sha1((string) $data['Student_ID']);
            $res = Cache::lock($lockKey, 30)->block(5, $persist);
        } else {
            $res = $persist();
        }

        $this->repository->clearCache();

        if (!empty($data['Student_ID'])) {
            Cache::forget("student_billing_{$data['Student_ID']}");
        }
        Cache::forget('finance_dashboard');
        Cache::forget('dashboard_finance');

        $this->enterpriseEvent->dispatch(
            'FINANCE', 
            'CREATE', 
            'INVOICE', 
            $data['Invoice_ID'], 
            \App\Support\ActorIdentity::required(), 
            ['FINANCE'], 
            !empty($data['Student_ID']) ? [$data['Student_ID']] : [], 
            $data
        );
        
        return $this->formatInvoiceRecord($data);
    }

    public function publish($id)
    {
        $invoice = $this->getById($id);
        if (!$invoice) {
            throw new Exception("Invoice tidak ditemukan.");
        }

        $currentStatus = trim($invoice['Status'] ?? 'Draft');
        if (strcasecmp($currentStatus, 'Draft') !== 0) {
            throw new Exception("Hanya invoice berstatus Draft yang dapat diterbitkan.");
        }

        $data = [
            'Status' => 'Waiting Payment',
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

        $this->enterpriseEvent->dispatch(
            'FINANCE',
            'UPDATE',
            'INVOICE',
            $id,
            \App\Support\ActorIdentity::required(),
            ['STUDENT', 'FINANCE'],
            !empty($invoice['Student_ID']) ? [$invoice['Student_ID']] : [],
            ['Status' => 'Waiting Payment', 'Previous_Status' => 'Draft']
        );

        $updatedInvoice = array_merge($invoice, $data);
        return $this->formatInvoiceRecord($updatedInvoice);
    }

    public function cancel($id)
    {
        $invoice = $this->getById($id);
        if (!$invoice) {
            throw new Exception("Invoice tidak ditemukan.");
        }

        $currentStatus = trim($invoice['Status'] ?? 'Draft');
        if (in_array(strtolower($currentStatus), ['paid', 'cancelled'])) {
            throw new Exception("Invoice yang sudah Lunas (Paid) atau Dibatalkan (Cancelled) tidak dapat dibatalkan lagi.");
        }

        $data = [
            'Status' => 'Cancelled',
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

        $this->enterpriseEvent->dispatch(
            'FINANCE',
            'UPDATE',
            'INVOICE',
            $id,
            \App\Support\ActorIdentity::required(),
            ['FINANCE'],
            !empty($invoice['Student_ID']) ? [$invoice['Student_ID']] : [],
            ['Status' => 'Cancelled', 'Previous_Status' => $currentStatus]
        );

        $updatedInvoice = array_merge($invoice, $data);
        return $this->formatInvoiceRecord($updatedInvoice);
    }

    public function update($id, array $data)
    {
        $invoice = $this->getById($id);
        if (!$invoice) {
            throw new Exception("Invoice tidak ditemukan.");
        }

        $currentStatus = trim($invoice['Status'] ?? 'Draft');
        if (strcasecmp($currentStatus, 'Draft') !== 0) {
            throw new Exception("Hanya invoice berstatus Draft yang dapat diubah nilainya atau rincian itemnya.");
        }

        // Process Items Array if provided
        $items = $data['items'] ?? [];
        if (!empty($items) && is_array($items)) {
            $processedItems = [];
            $grandTotal = 0;
            foreach ($items as $item) {
                $qty = (float)($item['qty'] ?? 1);
                $unitPrice = (float)($item['unit_price'] ?? 0);
                $discount = (float)($item['discount'] ?? 0);
                $tax = (float)($item['tax'] ?? 0);
                $subtotal = max(0.0, ($qty * $unitPrice) - $discount + $tax);
                $grandTotal += $subtotal;
                
                $processedItems[] = [
                    'description' => $item['description'] ?? 'Item Tagihan',
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'discount' => $discount,
                    'tax' => $tax,
                    'subtotal' => $subtotal
                ];
            }
            $data['Line_Items'] = json_encode($processedItems);
            $data['Amount'] = $grandTotal;
        }

        if ($this->isEducationCategory($data['Category'] ?? ($invoice['Category'] ?? ''))) {
            $data['Category'] = 'Biaya Pendidikan';
            $data['Student_ID'] = $data['Student_ID'] ?? ($invoice['Student_ID'] ?? '');
            $this->enforceEducationTuitionCap($data, $id);
        }

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

        $this->enterpriseEvent->dispatch(
            'FINANCE', 
            'UPDATE', 
            'INVOICE', 
            $id, 
            \App\Support\ActorIdentity::required(), 
            ['FINANCE'], 
            !empty($invoice['Student_ID']) ? [$invoice['Student_ID']] : [], 
            $data
        );

        $updatedInvoice = array_merge($invoice, $data);
        return $this->formatInvoiceRecord($updatedInvoice);
    }

    public function delete($id)
    {
        $invoice = $this->getById($id);
        if (!$invoice) {
            throw new Exception("Invoice tidak ditemukan.");
        }

        $currentStatus = trim($invoice['Status'] ?? 'Draft');
        if (strcasecmp($currentStatus, 'Draft') !== 0) {
            throw new Exception("Hanya invoice berstatus Draft yang dapat dihapus.");
        }

        $res = $this->repository->delete($id);
        if (!$res) {
            throw new Exception("Gagal menghapus invoice {$id}.");
        }
        $this->repository->clearCache();

        if (!empty($invoice['Student_ID'])) {
            Cache::forget("student_billing_{$invoice['Student_ID']}");
        }
        Cache::forget('finance_dashboard');
        Cache::forget('dashboard_finance');

        $this->enterpriseEvent->dispatch(
            'FINANCE',
            'DELETE',
            'INVOICE',
            $id,
            \App\Support\ActorIdentity::required(),
            ['FINANCE'],
            !empty($invoice['Student_ID']) ? [$invoice['Student_ID']] : [],
            []
        );

        return true;
    }
}
