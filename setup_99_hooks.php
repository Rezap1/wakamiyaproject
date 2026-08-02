<?php

// 1. Hooking into PayrollService for Tax Rate
$prFile = 'app/Services/HR/PayrollService.php';
$prContent = @file_get_contents($prFile);
if ($prContent) {
    if(strpos($prContent, 'SystemSettingService::class') === false) {
        $prContent = str_replace(
            "// Calculate Deductions",
            "\$taxRate = app(\App\Services\Core\SystemSettingService::class)->parameter('Payroll', 'Tax_Rate', 5) / 100;\n        // Calculate Deductions",
            $prContent
        );
        $prContent = str_replace(
            "'Tax' => \$baseSalary * 0.05",
            "'Tax' => \$baseSalary * \$taxRate",
            $prContent
        );
        file_put_contents($prFile, $prContent);
    }
}

// 2. Hooking into InvoiceService for Prefix
$invFile = 'app/Services/Finance/InvoiceService.php';
$invContent = @file_get_contents($invFile);
if ($invContent) {
    if(strpos($invContent, 'SystemSettingService::class') === false) {
        $invContent = str_replace(
            "uniqid('INV-')",
            "app(\App\Services\Core\SystemSettingService::class)->parameter('Finance', 'Invoice_Prefix', 'INV-') . strtoupper(substr(uniqid(), -6))",
            $invContent
        );
        file_put_contents($invFile, $invContent);
    }
}

echo "Settings Hooks Injected.\n";
?>
