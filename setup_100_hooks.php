<?php

function replaceHook($file, $searchPattern, $replacement) {
    if(!file_exists($file)) return false;
    $content = file_get_contents($file);
    // Remove old ApprovalService and AuditLogService hooks first to avoid duplication
    $content = preg_replace("/try \{ app\(\\\\App\\\\Services\\\\Core\\\\ApprovalService::class\)->submit.*?\n/s", "", $content);
    $content = preg_replace("/try \{ app\(\\\\App\\\\Services\\\\Core\\\\AuditLogService::class\)->log.*?\n/s", "", $content);
    
    // Inject EnterpriseAutomationService
    if(strpos($content, 'EnterpriseAutomationService') === false) {
        $content = str_replace($searchPattern, $searchPattern . "\n        " . $replacement, $content);
    }
    file_put_contents($file, $content);
    return true;
}

// 1. PayrollService
$prFile = 'app/Services/HR/PayrollService.php';
$prContent = @file_get_contents($prFile);
if($prContent) {
    $prContent = preg_replace("/try \{ app\(\\\\App\\\\Services\\\\Core\\\\ApprovalService::class\)->submit.*?\n/s", "", $prContent);
    $prContent = preg_replace("/try \{ app\(\\\\App\\\\Services\\\\Core\\\\AuditLogService::class\)->log.*?\n/s", "", $prContent);
    if(strpos($prContent, 'EnterpriseAutomationService') === false) {
        $prContent = str_replace(
            "\$this->repo->clearCache();",
            "\$this->repo->clearCache();\n        try { app(\App\Services\Core\EnterpriseAutomationService::class)->payrollGenerated(['Payroll_ID' => \$res['Payroll_ID'] ?? 'UNKNOWN']); } catch(\Exception \$e) {}",
            $prContent
        );
    }
    file_put_contents($prFile, $prContent);
}


// 2. InvoiceService
$invFile = 'app/Services/Finance/InvoiceService.php';
$invContent = @file_get_contents($invFile);
if($invContent) {
    $invContent = preg_replace("/try \{ app\(\\\\App\\\\Services\\\\Core\\\\ApprovalService::class\)->submit.*?\n/s", "", $invContent);
    $invContent = preg_replace("/try \{ app\(\\\\App\\\\Services\\\\Core\\\\AuditLogService::class\)->log.*?\n/s", "", $invContent);
    if(strpos($invContent, 'EnterpriseAutomationService') === false) {
        $invContent = str_replace(
            "return \$res;",
            "try { app(\App\Services\Core\EnterpriseAutomationService::class)->invoiceGenerated(['Invoice_ID' => \$res['Invoice_ID'] ?? 'UNKNOWN', 'Student_ID' => \$data['Student_ID'] ?? 'UNKNOWN']); } catch(\Exception \$e) {}\n        return \$res;",
            $invContent
        );
    }
    file_put_contents($invFile, $invContent);
}

// 3. DocumentService
$docFile = 'app/Services/Document/DocumentService.php';
$docContent = @file_get_contents($docFile);
if($docContent) {
    $docContent = preg_replace("/try \{ app\(\\\\App\\\\Services\\\\Core\\\\ApprovalService::class\)->submit.*?\n/s", "", $docContent);
    $docContent = preg_replace("/try \{ app\(\\\\App\\\\Services\\\\Core\\\\AuditLogService::class\)->log.*?\n/s", "", $docContent);
    if(strpos($docContent, 'EnterpriseAutomationService') === false) {
        $docContent = str_replace(
            "\$this->docRepo->clearCache();",
            "\$this->docRepo->clearCache();\n        try { app(\App\Services\Core\EnterpriseAutomationService::class)->documentGenerated(\$docId); } catch(\Exception \$e) {}",
            $docContent
        );
    }
    file_put_contents($docFile, $docContent);
}

// 4. AuthController
$authFile = 'app/Http/Controllers/Core/AuthController.php';
$authContent = @file_get_contents($authFile);
if($authContent) {
    $authContent = preg_replace("/try \{ app\(\\\\App\\\\Services\\\\Core\\\\AuditLogService::class\)->log.*?\n/s", "", $authContent);
    if(strpos($authContent, 'EnterpriseAutomationService') === false) {
        $authContent = str_replace(
            "return redirect()->intended(\$redirectRoute);",
            "try { app(\App\Services\Core\EnterpriseAutomationService::class)->writeAudit('Authentication', 'Login', 'User', \$user->Username ?? 'User', null, 'Success'); } catch(\Exception \$e) {}\n        return redirect()->intended(\$redirectRoute);",
            $authContent
        );
    }
    file_put_contents($authFile, $authContent);
}


echo "Enterprise Automation Hooks Injected.\n";
?>
