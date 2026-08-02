<?php
$payrollRepo = app(\App\Interfaces\GoogleSheets\PayrollRepositoryInterface::class);
$payrolls = $payrollRepo->getAll();
foreach ($payrolls as $p) {
    if (($p['Status'] ?? '') === 'Paid') {
        echo "Payroll ID: " . $p['Payroll_ID'] . "\n";
        echo "Proof: " . ($p['Payment_Proof'] ?? 'NONE') . "\n";
        echo "Notes: " . ($p['Notes'] ?? 'NONE') . "\n";
        echo "---\n";
    }
}
