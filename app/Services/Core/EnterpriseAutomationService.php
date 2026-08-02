<?php
namespace App\Services\Core;

use Illuminate\Support\Facades\Log;

class EnterpriseAutomationService
{
    public function studentCreated($studentData)
    {
        $this->sendNotification('FINANCE', 'New Student Registration', "Student {$studentData['Student_Name']} has registered. Please review for billing.", 'Information', 'Normal');
        $this->writeAudit('Student', 'Create_Student', 'Student_Record', $studentData['Student_ID'], null, 'Registered');
        $this->refreshDashboard();
    }

    public function invoiceGenerated($invoiceData)
    {
        $this->sendNotification($invoiceData['Student_ID'] ?? 'UNKNOWN', 'New Invoice Generated', "A new invoice ({$invoiceData['Invoice_ID']}) has been generated for you.", 'Information', 'Normal');
        $this->writeAudit('Finance', 'Generate_Invoice', 'Invoice', $invoiceData['Invoice_ID'], null, 'Generated');
        $this->refreshDashboard();
    }

    public function paymentVerified($paymentData)
    {
        $this->sendNotification($paymentData['Student_ID'] ?? 'UNKNOWN', 'Payment Verified', "Your payment ({$paymentData['Payment_ID']}) has been verified.", 'Success', 'Normal');
        $this->writeAudit('Finance', 'Verify_Payment', 'Payment', $paymentData['Payment_ID'], 'Pending', 'Verified');
        $this->refreshDashboard();
    }

    public function payrollGenerated($payrollData)
    {
        // Automation flow: Payroll generated -> Workflow -> Notification -> Audit
        try {
            app(\App\Services\Core\ApprovalService::class)->submit('Payroll', 'Payroll_Record', $payrollData['Payroll_ID'] ?? $payrollData['Employee_ID'], 'System', 'High');
        } catch (\Exception $e) {}
        
        $this->writeAudit('Payroll', 'Generate_Payroll', 'Payroll_Record', $payrollData['Payroll_ID'] ?? 'UNKNOWN', null, 'Generated');
        $this->refreshDashboard();
    }

    public function assessmentPublished($assessmentData)
    {
        $this->writeAudit('Academic', 'Publish_Assessment', 'Assessment', $assessmentData['Assessment_ID'], null, 'Published');
        $this->refreshDashboard();
    }

    public function scorePublished($scoreData)
    {
        $this->sendNotification($scoreData['Student_ID'] ?? 'UNKNOWN', 'Score Published', "Your score for {$scoreData['Subject_ID']} has been published.", 'Success', 'Normal');
        $this->writeAudit('Academic', 'Publish_Score', 'Score_Record', $scoreData['Score_ID'] ?? 'UNKNOWN', null, 'Published');
        $this->refreshDashboard();
    }

    public function attendanceRecorded($attendanceData)
    {
        $this->writeAudit('Academic', 'Record_Attendance', 'Attendance', $attendanceData['Attendance_ID'] ?? 'UNKNOWN', null, 'Recorded');
        $this->refreshDashboard();
    }

    public function documentGenerated($documentId)
    {
        try {
            app(\App\Services\Core\ApprovalService::class)->submit('Document', 'Document_Publish', $documentId, 'System', 'Normal');
        } catch (\Exception $e) {}
        
        $this->writeAudit('Document', 'Generate_Document', 'PDF', $documentId, null, 'Generated');
        $this->refreshDashboard();
    }

    public function workflowApproved($approvalId, $module, $referenceId)
    {
        $this->sendNotification('Requester', 'Workflow Approved', "Your request ($referenceId) has been approved.", 'Success', 'Normal');
        $this->writeAudit('Workflow', 'Approve_Workflow', $module, $approvalId, 'Waiting', 'Approved');
        $this->refreshDashboard();
    }

    public function sendNotification($target, $title, $message, $type, $priority)
    {
        try {
            // Updated to route through EnterpriseEventService to maintain architecture rules
            if ($target == 'FINANCE' || $target == 'HR' || $target == 'DIRECTOR' || $target == 'ADMINISTRATOR') {
                app(\App\Services\Core\EnterpriseEventService::class)->dispatch(
                    'SYSTEM', 'NOTIFY', 'SYSTEM', 'SYSTEM', 'SYSTEM', [$target], [], ['title' => $title, 'message' => $message, 'type' => $type, 'priority' => $priority]
                );
            } else {
                app(\App\Services\Core\EnterpriseEventService::class)->dispatch(
                    'SYSTEM', 'NOTIFY', 'SYSTEM', 'SYSTEM', 'SYSTEM', [], [$target], ['title' => $title, 'message' => $message, 'type' => $type, 'priority' => $priority]
                );
            }
        } catch (\Exception $e) {
            Log::error("Automation Notification Failed: " . $e->getMessage());
        }
    }

    public function writeAudit($module, $action, $refType, $refId, $old, $new)
    {
        try {
            app(\App\Services\Core\AuditLogService::class)->log($module, $action, $refType, $refId, $old, $new);
        } catch (\Exception $e) {
            Log::error("Automation Audit Failed: " . $e->getMessage());
        }
    }

    public function refreshDashboard()
    {
        // Centralized cache clearing for enterprise dashboards is now handled by EnterpriseEventService,
        // but for legacy manual clearing here we can call DashboardCacheService
        try {
            app(\App\Services\Core\DashboardCacheService::class)->clearAll();
        } catch (\Exception $e) {
            // fallback
            \Illuminate\Support\Facades\Cache::forget('enterprise_dashboard');
            \Illuminate\Support\Facades\Cache::forget('enterprise_statistics');
        }
    }
}