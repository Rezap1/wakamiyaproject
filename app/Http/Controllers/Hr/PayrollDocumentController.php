<?php
namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Services\HR\PayrollService;

class PayrollDocumentController extends Controller
{
    protected $payrollService;

    public function __construct(PayrollService $payrollService)
    {
        $this->payrollService = $payrollService;
    }

    public function showSlip($id)
    {
        $payroll = $this->payrollService->getById($id);
        if (!$payroll) abort(404);
        
        $slipNumber = $this->payrollService->GenerateSalarySlip($id);
        // Note: For now, it will return a preview page (stub), not a real PDF download.
        return view('hr.payroll.slip', compact('payroll', 'slipNumber'));
    }
}
