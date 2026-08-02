<?php
// Inject NotificationService calls into existing services safely

function injectHook($file, $searchPattern, $hookCode, $before = false) {
    if(!file_exists($file)) return false;
    $content = file_get_contents($file);
    if(strpos($content, 'app(\App\Services\Core\NotificationService::class)') !== false && strpos($content, md5($hookCode)) === false) {
        // Maybe already injected? Let's just blindly inject but with a marker if we want, or just rely on exact matches
    }
    
    // We will inject the hook code right after the match (or before)
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

// 1. PayrollService
$file = 'app/Services/HR/PayrollService.php';
// After create
$hookCreate = <<<'EOT'
        try {
            $notifService = app(\App\Services\Core\NotificationService::class);
            $notifService->NotifyUser($data['Employee_ID'], 'Payroll Generated', "Your payroll {$res['Payroll_Number']} has been generated.", 'Payroll', 'Normal');
        } catch (\Exception $e) {}
EOT;
injectHook($file, '/return \$res; \/\/ Return the newly created payroll record/i', $hookCreate, true); // wait, it might not match exact.
// Let's just use string replace on specific lines instead of regex if possible.

// Actually, let's just make a very targeted replacement for each file

// 1. PayrollService: GeneratePayroll
$content = @file_get_contents($file);
if($content) {
    if(strpos($content, 'Payroll Generated') === false) {
        $content = str_replace(
            '$this->repo->clearCache();',
            "\$this->repo->clearCache();\n        try { app(\App\Services\Core\NotificationService::class)->NotifyUser(\$data['Employee_ID'] ?? '', 'Payroll Generated', 'Your payroll has been generated.', 'Payroll', 'Normal', '/payrolls'); } catch (\Exception \$e) {}",
            $content
        );
        file_put_contents($file, $content);
    }
}

// 2. DocumentService: GenerateDocument
$file = 'app/Services/Document/DocumentService.php';
$content = @file_get_contents($file);
if($content) {
    if(strpos($content, 'Document Generated') === false) {
        $content = str_replace(
            '$this->docRepo->clearCache();',
            "\$this->docRepo->clearCache();\n        try { app(\App\Services\Core\NotificationService::class)->NotifyRole('ADMINISTRATOR', 'Document Generated', 'A new document has been generated.', 'Document', 'Normal', '/documents'); } catch (\Exception \$e) {}",
            $content
        );
        file_put_contents($file, $content);
    }
}

echo "Hooks injected.\n";
?>
