<?php

$dirConfig = 'config';
$dirRepo = 'app/Repositories/GoogleSheets';
$dirInterface = 'app/Interfaces/GoogleSheets';
$dirService = 'app/Services/Finance';
$dirCtrlCore = 'app/Http/Controllers/Core';
$dirCtrlFinance = 'app/Http/Controllers/Finance';
$dirCtrlStudent = 'app/Http/Controllers/Student';

if(!is_dir($dirService)) mkdir($dirService, 0755, true);
if(!is_dir($dirCtrlFinance)) mkdir($dirCtrlFinance, 0755, true);
if(!is_dir($dirCtrlStudent)) mkdir($dirCtrlStudent, 0755, true);

// 1. Config
$financeConfig = <<<'EOT'
<?php

return [
    'categories' => [
        'Registration Fee',
        'Education Fee',
        'Dormitory Fee',
        'Japanese Class',
        'JLPT Fee',
        'JFT Basic Fee',
        'SSW Examination',
        'Medical Checkup',
        'Visa Fee',
        'COE Processing',
        'Administration',
        'Other Charges'
    ],
    'invoice_status' => [
        'Draft',
        'Waiting Payment',
        'Partial Paid',
        'Paid',
        'Overdue',
        'Cancelled',
        'Refund'
    ],
    'payment_status' => [
        'Waiting Verification',
        'Verified',
        'Rejected'
    ],
    'invoice_prefix' => 'INV',
    'receipt_prefix' => 'RCT'
];
EOT;
file_put_contents("$dirConfig/finance.php", $financeConfig);

// 2. Interfaces
$invInterface = <<<'EOT'
<?php
namespace App\Interfaces\GoogleSheets;
interface InvoiceRepositoryInterface {
    public function getAll();
    public function getById($id);
    public function create(array $data);
    public function update($id, array $data);
    public function delete($id);
}
EOT;
file_put_contents("$dirInterface/InvoiceRepositoryInterface.php", $invInterface);

$payInterface = <<<'EOT'
<?php
namespace App\Interfaces\GoogleSheets;
interface PaymentRepositoryInterface {
    public function getAll();
    public function getById($id);
    public function create(array $data);
    public function update($id, array $data);
    public function delete($id);
}
EOT;
file_put_contents("$dirInterface/PaymentRepositoryInterface.php", $payInterface);

// 3. Repositories
$invRepo = <<<'EOT'
<?php
namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;

class InvoiceRepository extends BaseSheetRepository implements InvoiceRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'FINANCE_INVOICE';
        $this->cacheKey = 'finance_invoice_sheet';
        $this->primaryKey = 'Invoice_ID';
    }

    public function getAll()
    {
        return $this->fetchAll();
    }

    public function getById($id)
    {
        $items = $this->fetchAll();
        return $items->firstWhere($this->primaryKey, $id);
    }

    public function create(array $data)
    {
        return $this->append($data);
    }

    public function update($id, array $data)
    {
        return $this->updateRow($id, $data);
    }

    public function delete($id)
    {
        return $this->updateRow($id, ['Status' => 'Cancelled']);
    }
}
EOT;
file_put_contents("$dirRepo/InvoiceRepository.php", $invRepo);

$payRepo = <<<'EOT'
<?php
namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;

class PaymentRepository extends BaseSheetRepository implements PaymentRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'FINANCE_PAYMENT';
        $this->cacheKey = 'finance_payment_sheet';
        $this->primaryKey = 'Payment_ID';
    }

    public function getAll()
    {
        return $this->fetchAll();
    }

    public function getById($id)
    {
        $items = $this->fetchAll();
        return $items->firstWhere($this->primaryKey, $id);
    }

    public function create(array $data)
    {
        return $this->append($data);
    }

    public function update($id, array $data)
    {
        return $this->updateRow($id, $data);
    }

    public function delete($id)
    {
        return $this->updateRow($id, ['Status' => 'Rejected']);
    }
}
EOT;
file_put_contents("$dirRepo/PaymentRepository.php", $payRepo);

// 4. Services
$invService = <<<'EOT'
<?php
namespace App\Services\Finance;

use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Services\Core\NotificationService;

class InvoiceService
{
    protected $repository, $notificationService;

    public function __construct(InvoiceRepositoryInterface $repository, NotificationService $notificationService)
    {
        $this->repository = $repository;
        $this->notificationService = $notificationService;
    }

    public function getAll() { return $this->repository->getAll(); }
    public function getById($id) { return $this->repository->getById($id); }

    public function generateInvoiceNumber()
    {
        $prefix = config('finance.invoice_prefix', 'INV');
        $year = date('Y');
        $all = $this->repository->getAll();
        $count = $all->count() + 1;
        return sprintf("%s-%s-%06d", $prefix, $year, $count);
    }

    public function create(array $data)
    {
        if (empty($data['Invoice_ID'])) {
            $data['Invoice_ID'] = $this->generateInvoiceNumber();
        }
        
        $data['Created_At'] = now()->toDateTimeString();
        $res = $this->repository->create($data);
        $this->repository->clearCache();

        if (isset($data['Status']) && $data['Status'] == 'Waiting Payment') {
            // Document Prep: Will generate PDF later
            // Event Notification Prep
            $this->notificationService->notifyUser($data['Student_ID'], 'New Invoice', "Invoice {$data['Invoice_ID']} has been issued.");
        }

        return $res;
    }

    public function update($id, array $data)
    {
        $data['Updated_At'] = now()->toDateTimeString();
        $res = $this->repository->update($id, $data);
        $this->repository->clearCache();
        return $res;
    }
}
EOT;
file_put_contents("$dirService/InvoiceService.php", $invService);

$payService = <<<'EOT'
<?php
namespace App\Services\Finance;

use App\Interfaces\GoogleSheets\PaymentRepositoryInterface;
use App\Interfaces\GoogleSheets\InvoiceRepositoryInterface;
use App\Services\Core\NotificationService;
use Exception;

class PaymentService
{
    protected $paymentRepository, $invoiceRepository, $notificationService;

    public function __construct(
        PaymentRepositoryInterface $paymentRepository,
        InvoiceRepositoryInterface $invoiceRepository,
        NotificationService $notificationService
    ) {
        $this->paymentRepository = $paymentRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->notificationService = $notificationService;
    }

    public function getAll() { return $this->paymentRepository->getAll(); }
    public function getById($id) { return $this->paymentRepository->getById($id); }

    public function generateReceiptNumber()
    {
        $prefix = config('finance.receipt_prefix', 'RCT');
        $year = date('Y');
        $all = $this->paymentRepository->getAll();
        $count = $all->count() + 1;
        return sprintf("%s-%s-%06d", $prefix, $year, $count);
    }

    public function submitPayment(array $data)
    {
        if (empty($data['Payment_ID'])) {
            $data['Payment_ID'] = $this->generateReceiptNumber();
        }
        
        $data['Status'] = 'Waiting Verification';
        $data['Payment_Date'] = now()->toDateTimeString();
        $data['Created_At'] = now()->toDateTimeString();

        $res = $this->paymentRepository->create($data);
        $this->paymentRepository->clearCache();

        // Notification Prep: Notify Finance
        $this->notificationService->notifyRole('FINANCE', 'New Payment Received', "Payment {$data['Payment_ID']} needs verification.");
        
        return $res;
    }

    public function verifyPayment($paymentId, $verifiedBy, $status, $notes = '')
    {
        $payment = $this->getById($paymentId);
        if (!$payment) throw new Exception("Payment not found");

        $data = [
            'Status' => $status,
            'Verified_By' => $verifiedBy,
            'Verified_At' => now()->toDateTimeString(),
            'Notes' => $notes,
            'Updated_At' => now()->toDateTimeString()
        ];

        $res = $this->paymentRepository->update($paymentId, $data);
        $this->paymentRepository->clearCache();

        if ($status == 'Verified') {
            // Update Invoice Status
            $invoiceId = $payment['Invoice_ID'] ?? null;
            if ($invoiceId) {
                $invoice = $this->invoiceRepository->getById($invoiceId);
                if ($invoice) {
                    $invStatus = 'Paid'; // Simplified, assumes full payment
                    $this->invoiceRepository->update($invoiceId, ['Status' => $invStatus, 'Updated_At' => now()->toDateTimeString()]);
                    $this->invoiceRepository->clearCache();
                }
            }

            // Notification Prep: Notify Student
            $this->notificationService->notifyUser($payment['Student_ID'], 'Payment Verified', "Payment {$paymentId} is verified.");
        }

        return $res;
    }
}
EOT;
file_put_contents("$dirService/PaymentService.php", $payService);

echo "Backend Foundation created successfully.\n";
?>
