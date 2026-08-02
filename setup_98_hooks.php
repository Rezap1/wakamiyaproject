<?php

// 1. Hooking into Auth Controller (Login)
$authFile = 'app/Http/Controllers/Core/AuthController.php';
$authContent = @file_get_contents($authFile);
if ($authContent && strpos($authContent, 'AuditLogService::class') === false) {
    // Inject at the end of successful login
    $authContent = str_replace(
        "return redirect()->intended(\$redirectRoute);",
        "try { app(\App\Services\Core\AuditLogService::class)->log('Authentication', 'Login', 'User', \$user->Username ?? 'User'); } catch(\Exception \$e) {}\n        return redirect()->intended(\$redirectRoute);",
        $authContent
    );
    // Inject at logout
    $authContent = str_replace(
        "return redirect()->route('login');",
        "try { app(\App\Services\Core\AuditLogService::class)->log('Authentication', 'Logout', 'User', auth()->user()->Username ?? 'User'); } catch(\Exception \$e) {}\n        return redirect()->route('login');",
        $authContent
    );
    file_put_contents($authFile, $authContent);
}

// 2. Hooking into WorkflowService / ApprovalService
$appFile = 'app/Services/Core/ApprovalService.php';
$appContent = @file_get_contents($appFile);
if ($appContent && strpos($appContent, 'AuditLogService::class') === false) {
    // Hook Submit
    $appContent = str_replace(
        "\$this->historyService->createHistory(\$data['Approval_ID'], \$wfId, 'Submit', 'Draft', 'Waiting Approval', 'Submitted for approval.', \$requesterEmail);",
        "\$this->historyService->createHistory(\$data['Approval_ID'], \$wfId, 'Submit', 'Draft', 'Waiting Approval', 'Submitted for approval.', \$requesterEmail);\n        try { app(\App\Services\Core\AuditLogService::class)->log('Workflow', 'Submit_Approval', \$referenceType, \$referenceId, null, \$data['Approval_ID']); } catch(\Exception \$e) {}",
        $appContent
    );
    // Hook Approve
    $appContent = str_replace(
        "\$this->historyService->createHistory(\$id, \$app['Workflow_ID'], 'Approve', \$oldStatus, 'Approved', \$remarks, \$userEmail);",
        "\$this->historyService->createHistory(\$id, \$app['Workflow_ID'], 'Approve', \$oldStatus, 'Approved', \$remarks, \$userEmail);\n        try { app(\App\Services\Core\AuditLogService::class)->log('Workflow', 'Approve_Request', \$app['Reference_Type'] ?? 'Unknown', \$app['Reference_ID'] ?? 'Unknown', \$oldStatus, 'Approved'); } catch(\Exception \$e) {}",
        $appContent
    );
    // Hook Reject
    $appContent = str_replace(
        "\$this->historyService->createHistory(\$id, \$app['Workflow_ID'], 'Reject', \$oldStatus, 'Rejected', \$remarks, \$userEmail);",
        "\$this->historyService->createHistory(\$id, \$app['Workflow_ID'], 'Reject', \$oldStatus, 'Rejected', \$remarks, \$userEmail);\n        try { app(\App\Services\Core\AuditLogService::class)->log('Workflow', 'Reject_Request', \$app['Reference_Type'] ?? 'Unknown', \$app['Reference_ID'] ?? 'Unknown', \$oldStatus, 'Rejected'); } catch(\Exception \$e) {}",
        $appContent
    );
    file_put_contents($appFile, $appContent);
}

echo "Audit Logs Hooks Injected.\n";
?>
