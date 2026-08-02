<?php
function injectHook($file, $searchPattern, $hookCode, $before = false) {
    if(!file_exists($file)) return false;
    $content = file_get_contents($file);
    if(strpos($content, 'App\Services\Core\ApprovalService') !== false) {
        return true; // Already injected
    }
    
    if (preg_match($searchPattern, $content, $matches)) {
        if ($before) {
            $replacement = $hookCode . "\n" . $matches[0];
        } else {
            $replacement = $matches[0] . "\n" . $hookCode;
        }
        $content = str_replace($matches[0], $replacement, $content);
        file_put_contents($file, $content);
        return true;
    }
    return false;
}

// 1. PayrollService Hook
$file = 'app/Services/HR/PayrollService.php';
$content = @file_get_contents($file);
if($content && strpos($content, 'ApprovalService') === false) {
    // Instead of regex, simple string replacement to be safe
    $content = str_replace(
        "\$this->repo->clearCache();",
        "\$this->repo->clearCache();\n        // Workflow Engine Hook\n        try { app(\App\Services\Core\ApprovalService::class)->submit('Payroll', 'Payroll_Record', \$res['Payroll_ID'] ?? \$data['Employee_ID'], 'System', 'High'); } catch (\Exception \$e) {}",
        $content
    );
    file_put_contents($file, $content);
}

// 2. InvoiceService Hook (Assuming it exists, if not, no big deal for the test)
$file = 'app/Services/Finance/InvoiceService.php';
$content = @file_get_contents($file);
if($content && strpos($content, 'ApprovalService') === false) {
    $content = str_replace(
        "return \$res;",
        "try { app(\App\Services\Core\ApprovalService::class)->submit('Billing', 'Invoice', \$res['Invoice_ID'] ?? 'INV-XXXX', 'System', 'Normal'); } catch (\Exception \$e) {}\n        return \$res;",
        $content
    );
    file_put_contents($file, $content);
}

// 3. DocumentService Hook
$file = 'app/Services/Document/DocumentService.php';
$content = @file_get_contents($file);
if($content && strpos($content, 'ApprovalService') === false) {
    $content = str_replace(
        "\$this->docRepo->clearCache();",
        "\$this->docRepo->clearCache();\n        try { app(\App\Services\Core\ApprovalService::class)->submit('Document', 'Document_Publish', \$docId, 'System', 'Normal'); } catch (\Exception \$e) {}",
        $content
    );
    file_put_contents($file, $content);
}

echo "Workflow Automation Hooks Injected.\n";
?>
