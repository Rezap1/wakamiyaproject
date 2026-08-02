<?php

$dirInterface = 'app/Interfaces/GoogleSheets';
$dirRepo = 'app/Repositories/GoogleSheets';
$dirService = 'app/Services/HR';

if(!is_dir($dirService)) mkdir($dirService, 0755, true);

// 1. Interfaces
$payInterface = <<<'EOT'
<?php
namespace App\Interfaces\GoogleSheets;
interface PayrollRepositoryInterface {
    public function getAll();
    public function getById($id);
    public function create(array $data);
    public function update($id, array $data);
    public function delete($id);
}
EOT;
file_put_contents("$dirInterface/PayrollRepositoryInterface.php", $payInterface);

$salInterface = <<<'EOT'
<?php
namespace App\Interfaces\GoogleSheets;
interface SalaryComponentRepositoryInterface {
    public function getAll();
    public function getById($id);
    public function create(array $data);
    public function update($id, array $data);
    public function delete($id);
}
EOT;
file_put_contents("$dirInterface/SalaryComponentRepositoryInterface.php", $salInterface);

// 2. Repositories
$payRepo = <<<'EOT'
<?php
namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\PayrollRepositoryInterface;

class PayrollRepository extends BaseSheetRepository implements PayrollRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_PAYROLL';
        $this->cacheKey = 'payroll_sheet';
        $this->primaryKey = 'Payroll_ID';
    }

    public function getAll() { return $this->fetchAll(); }
    public function getById($id) { return $this->fetchAll()->firstWhere($this->primaryKey, $id); }
    public function create(array $data) { return $this->append($data); }
    public function update($id, array $data) { return $this->updateRow($id, $data); }
    public function delete($id) { return $this->updateRow($id, ['Status' => 'Cancelled']); }
}
EOT;
file_put_contents("$dirRepo/PayrollRepository.php", $payRepo);

$salRepo = <<<'EOT'
<?php
namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\SalaryComponentRepositoryInterface;

class SalaryComponentRepository extends BaseSheetRepository implements SalaryComponentRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_SALARY_COMPONENT';
        $this->cacheKey = 'salary_component_sheet';
        $this->primaryKey = 'Component_ID';
    }

    public function getAll() { return $this->fetchAll(); }
    public function getById($id) { return $this->fetchAll()->firstWhere($this->primaryKey, $id); }
    public function create(array $data) { return $this->append($data); }
    public function update($id, array $data) { return $this->updateRow($id, $data); }
    public function delete($id) { return $this->updateRow($id, ['Status' => 'Inactive']); }
}
EOT;
file_put_contents("$dirRepo/SalaryComponentRepository.php", $salRepo);

// 3. Service
$payService = <<<'EOT'
<?php
namespace App\Services\HR;

use App\Interfaces\GoogleSheets\PayrollRepositoryInterface;
use App\Interfaces\GoogleSheets\SalaryComponentRepositoryInterface;

class PayrollService
{
    protected $payrollRepo, $salaryRepo;

    public function __construct(
        PayrollRepositoryInterface $payrollRepo,
        SalaryComponentRepositoryInterface $salaryRepo
    ) {
        $this->payrollRepo = $payrollRepo;
        $this->salaryRepo = $salaryRepo;
    }

    public function getAll() { return $this->payrollRepo->getAll(); }
    public function getById($id) { return $this->payrollRepo->getById($id); }

    public function GeneratePayrollNumber()
    {
        $year = date('Y');
        $count = $this->payrollRepo->getAll()->count() + 1;
        return sprintf("PAY-%s-%06d", $year, $count);
    }

    public function GenerateDocumentNumber()
    {
        $year = date('Y');
        $count = $this->payrollRepo->getAll()->count() + 1;
        return sprintf("DOC-PAY-%s-%06d", $year, $count);
    }

    public function CalculateBasicSalary($employeeId) { return 0; /* Fetched from employee data if implemented */ }
    public function CalculateAllowance($employeeId) { return 0; }
    public function CalculateBonus($employeeId) { return 0; }
    public function CalculateOvertime($employeeId) { return 0; }
    public function CalculateDeduction($employeeId) { return 0; }
    public function CalculateTax($grossSalary) { return $grossSalary * 0.05; } // Stub 5%
    public function CalculateBPJS($grossSalary) { return $grossSalary * 0.02; } // Stub 2%
    
    public function CalculateNetSalary(array $data)
    {
        $basic = floatval($data['Basic_Salary'] ?? 0);
        $allowance = floatval($data['Allowance'] ?? 0);
        $bonus = floatval($data['Bonus'] ?? 0);
        $overtime = floatval($data['Overtime'] ?? 0);
        
        $gross = $basic + $allowance + $bonus + $overtime;

        $deduction = floatval($data['Deduction'] ?? 0);
        $tax = floatval($data['Tax'] ?? $this->CalculateTax($gross));
        $bpjs = floatval($data['BPJS'] ?? $this->CalculateBPJS($gross));

        $net = $gross - $deduction - $tax - $bpjs;
        return [
            'Gross' => $gross,
            'Tax' => $tax,
            'BPJS' => $bpjs,
            'Net_Salary' => $net
        ];
    }

    public function GenerateSalarySlip($payrollId)
    {
        return sprintf("SLIP-%s-%06d", date('Y'), rand(1,999999));
    }

    public function PrepareDocument($payrollId)
    {
        return [
            'Generated_Document' => 'TRUE',
            'Document_Number' => $this->GenerateDocumentNumber()
        ];
    }

    public function PrepareNotification($payrollId, $status)
    {
        // Hook for Notification Engine
        return true;
    }

    public function processPayroll(array $data)
    {
        $data['Payroll_ID'] = uniqid('PRL_');
        $data['Payroll_Number'] = $this->GeneratePayrollNumber();
        $data['Status'] = 'Draft';
        $data['Created_At'] = now()->toDateTimeString();
        
        $calculation = $this->CalculateNetSalary($data);
        $data['Tax'] = $calculation['Tax'];
        $data['BPJS'] = $calculation['BPJS'];
        $data['Net_Salary'] = $calculation['Net_Salary'];

        $docData = $this->PrepareDocument($data['Payroll_ID']);
        $data['Generated_Document'] = $docData['Generated_Document'];
        $data['Document_Number'] = $docData['Document_Number'];

        $res = $this->payrollRepo->create($data);
        $this->payrollRepo->clearCache();

        $this->PrepareNotification($data['Payroll_ID'], 'Payroll Generated');

        return $res;
    }

    public function updateStatus($id, $status, $user)
    {
        $data = [
            'Status' => $status,
            'Updated_At' => now()->toDateTimeString()
        ];
        if ($status == 'Approved') {
            $data['Approved_By'] = $user;
            $data['Approved_Date'] = now()->toDateTimeString();
            $this->PrepareNotification($id, 'Payroll Approved');
        } elseif ($status == 'Paid') {
            $data['Paid_Date'] = now()->toDateTimeString();
            $this->PrepareNotification($id, 'Payroll Paid');
        }

        $res = $this->payrollRepo->update($id, $data);
        $this->payrollRepo->clearCache();
        return $res;
    }
}
EOT;
file_put_contents("$dirService/PayrollService.php", $payService);

echo "Backend Foundation created successfully.\n";
?>
