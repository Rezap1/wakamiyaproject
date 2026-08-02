<?php
namespace App\Services\Finance;

use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use Exception;

class InvoiceService
{
    protected $repository;
    protected $enterpriseEvent;
    protected $studentRepository;
    protected $companyRepository;

    public function __construct(
        InvoiceRepositoryInterface $repository, 
        EnterpriseEventService $enterpriseEvent,
        StudentRepositoryInterface $studentRepository,
        CompanyRepositoryInterface $companyRepository
    ) {
        $this->repository = $repository;
        $this->enterpriseEvent = $enterpriseEvent;
        $this->studentRepository = $studentRepository;
        $this->companyRepository = $companyRepository;
    }

    public function getAll() 
    { 
        $invoices = collect($this->repository->getAll())->where('Is_Active', '!=', 'FALSE')->values();
        $user = auth()->user();
        
        if ($user && ($user->Role ?? '') === 'STUDENT') {
            $student = collect($this->studentRepository->fetchAll())->firstWhere('User_ID', $user->User_ID);
            if ($student) {
                return $invoices->where('Student_ID', $student['Student_ID'])->values();
            }
            return collect(); // Return empty if student profile not found
        }
        
        return $invoices;
    }

    public function getById($id) { 
        return $this->repository->getById($id); 
    }

    public function generateInvoiceNumber($type = 'STUDENT')
    {
        $prefix = $type === 'COMPANY' ? 'INV-CORP' : 'INV-STU';
        $year = date('Y');
        $all = $this->repository->getAll();
        $count = collect($all)->count() + 1;
        return sprintf("%s-%s-%06d", $prefix, $year, $count);
    }

    public function create(array $data)
    {
        $data['Invoice_Type'] = $data['Invoice_Type'] ?? 'STUDENT';
        $this->validateDependencies($data);
        
        if (empty($data['Invoice_ID'])) {
            $data['Invoice_ID'] = $this->generateInvoiceNumber($data['Invoice_Type']);
        } else {
            $existing = $this->repository->getById($data['Invoice_ID']);
            if ($existing) {
                throw new Exception("Invoice ID {$data['Invoice_ID']} sudah terdaftar.");
            }
        }
        
        $data['Is_Active'] = 'TRUE';
        $data['Created_By'] = auth()->id() ?? 'SYSTEM';
        $data['Created_At'] = now()->toDateTimeString();
        $data['Updated_At'] = now()->toDateTimeString();
        
        $res = $this->repository->create($data);
        $this->repository->clearCache();
        
        if (!empty($data['Student_ID'])) {
            \Illuminate\Support\Facades\Cache::forget("student_billing_{$data['Student_ID']}");
        }
        \Illuminate\Support\Facades\Cache::forget('finance_dashboard');

        if (isset($data['Status']) && $data['Status'] == 'Waiting Payment' && $data['Invoice_Type'] === 'STUDENT') {
            $this->enterpriseEvent->dispatch('FINANCE', 'CREATE', 'INVOICE', $res['Invoice_ID'] ?? $data['Invoice_ID'], auth()->id() ?? 'SYSTEM', ['STUDENT'], [$data['Student_ID']], $data);
        }

        try { 
            if ($data['Invoice_Type'] === 'STUDENT') {
                app(\App\Services\Core\EnterpriseAutomationService::class)->invoiceGenerated([
                    'Invoice_ID' => $res['Invoice_ID'] ?? 'UNKNOWN', 
                    'Student_ID' => $data['Student_ID'] ?? 'UNKNOWN'
                ]); 
            }
        } catch(\Exception $e) {}
        
        return $res;
    }

    public function update($id, array $data)
    {
        $invoice = $this->repository->getById($id);
        if (!$invoice) throw new Exception("Invoice not found.");
        
        $data['Invoice_Type'] = $data['Invoice_Type'] ?? $invoice['Invoice_Type'] ?? 'STUDENT';
        $this->validateDependencies($data);
        
        // State Machine: Disable manual edit if not Draft
        if (($invoice['Status'] ?? 'Draft') !== 'Draft') {
            throw new Exception("Invoice tidak dapat diubah karena statusnya " . ($invoice['Status'] ?? 'Draft'));
        }

        // Prevent mass assignment of Status
        unset($data['Status']);

        $data['Updated_At'] = now()->toDateTimeString();
        $res = $this->repository->update($id, $data);
        $this->repository->clearCache();
        
        if (!empty($invoice['Student_ID'])) {
            \Illuminate\Support\Facades\Cache::forget("student_billing_{$invoice['Student_ID']}");
        }
        \Illuminate\Support\Facades\Cache::forget('finance_dashboard');
        
        try { 
            if (($res['Invoice_Type'] ?? 'STUDENT') === 'STUDENT') {
                app(\App\Services\Core\EnterpriseAutomationService::class)->invoiceGenerated([
                    'Invoice_ID' => $res['Invoice_ID'] ?? 'UNKNOWN', 
                    'Student_ID' => $res['Student_ID'] ?? 'UNKNOWN'
                ]); 
            }
        } catch(\Exception $e) {}
        
        return $res;
    }

    protected function validateDependencies(array $data)
    {
        $type = $data['Invoice_Type'] ?? 'STUDENT';

        if ($type === 'STUDENT') {
            if (empty($data['Student_ID'])) {
                throw new Exception("Student ID wajib diisi untuk Invoice Type STUDENT.");
            }
            $student = $this->studentRepository->findById($data['Student_ID']);
            if (!$student || ($student['Is_Active'] ?? 'TRUE') === 'FALSE') {
                throw new Exception("Siswa tidak valid atau sedang tidak aktif.");
            }
        } elseif ($type === 'COMPANY') {
            if (empty($data['Company_ID'])) {
                throw new Exception("Company ID wajib diisi untuk Invoice Type COMPANY.");
            }
            $company = $this->companyRepository->findById($data['Company_ID']);
            if (!$company || ($company['Is_Active'] ?? 'TRUE') === 'FALSE') {
                throw new Exception("Perusahaan tidak valid atau sedang tidak aktif.");
            }
        } else {
            // PAYROLL or OTHER might not need specific entity ID validation for now
            if (empty($data['Student_ID']) && empty($data['Company_ID']) && empty($data['Reference_ID'])) {
                throw new Exception("Minimal satu entitas (Student_ID, Company_ID, atau Reference_ID) harus diisi.");
            }
        }
    }

    public function publish($id)
    {
        $invoice = $this->repository->getById($id);
        if (!$invoice) throw new Exception("Invoice not found.");
        if (($invoice['Status'] ?? '') !== 'Draft') throw new Exception("Only Draft invoices can be published.");

        $this->repository->update($id, [
            'Status' => 'Waiting Payment', 
            'Published_At' => now()->toDateTimeString(),
            'Updated_At' => now()->toDateTimeString()
        ]);
        $this->repository->clearCache();
        
        if (!empty($invoice['Student_ID'])) {
            \Illuminate\Support\Facades\Cache::forget("student_billing_{$invoice['Student_ID']}");
        }
        
        if (($invoice['Invoice_Type'] ?? 'STUDENT') === 'STUDENT' && !empty($invoice['Student_ID'])) {
            $amount = number_format($invoice['Amount'] ?? 0, 0, ',', '.');
            $category = $invoice['Category'] ?? 'Pendidikan';
            $message = "Tagihan Baru: Anda memiliki tagihan {$category} sebesar Rp {$amount}. Mohon segera lakukan pembayaran.";
            
            $this->enterpriseEvent->dispatch('FINANCE', 'UPDATE', 'INVOICE', $id, auth()->id() ?? 'SYSTEM', ['STUDENT'], [$invoice['Student_ID']], ['Message' => $message, 'Action_URL' => route('student.billing.index')]);
        }
        
        // Phase 10.5: Generate PDF Invoice
        try {
            $student = [];
            $company = [];
            if (!empty($invoice['Student_ID'])) {
                $student = $this->studentRepository->findById($invoice['Student_ID']) ?? [];
            }
            if (!empty($invoice['Company_ID'])) {
                $company = $this->companyRepository->findById($invoice['Company_ID']) ?? [];
            }
            
            $docAutomation = app(\App\Services\Core\DocumentAutomationService::class);
            $docAutomation->generateDocument(
                'Invoice',
                'Invoice',
                $id,
                ['invoice' => $invoice, 'student' => $student, 'company' => $company],
                'pdf.invoice',
                auth()->user()->email ?? 'System'
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to generate PDF for Invoice {$id}: " . $e->getMessage());
        }

        return $invoice;
    }

    public function sendNotification($id, $message = null)
    {
        $invoice = $this->repository->getById($id);
        if (!$invoice) throw new Exception("Invoice not found.");
        
        if (($invoice['Invoice_Type'] ?? 'STUDENT') === 'STUDENT' && !empty($invoice['Student_ID'])) {
            $amount = number_format($invoice['Amount'] ?? 0, 0, ',', '.');
            $category = $invoice['Category'] ?? 'Pendidikan';
            
            if (empty($message)) {
                $message = "Peringatan Tagihan: Anda memiliki tagihan {$category} sebesar Rp {$amount} yang belum dibayar. Mohon hubungi bagian Keuangan.";
            }
            
            $this->enterpriseEvent->dispatch('FINANCE', 'UPDATE', 'INVOICE', $id, auth()->id() ?? 'SYSTEM', ['STUDENT'], [$invoice['Student_ID']], ['Message' => $message, 'Action_URL' => route('student.billing.index')]);
        }
        
        return $invoice;
    }

    public function delete($id)
    {
        $invoice = $this->repository->getById($id);
        if (!$invoice) throw new Exception("Invoice not found.");
        
        $result = $this->repository->delete($id);
        $this->repository->clearCache();
        
        if (!empty($invoice['Student_ID'])) {
            \Illuminate\Support\Facades\Cache::forget("student_billing_{$invoice['Student_ID']}");
        }
        \Illuminate\Support\Facades\Cache::forget('finance_dashboard');
        
        return $result;
    }
}