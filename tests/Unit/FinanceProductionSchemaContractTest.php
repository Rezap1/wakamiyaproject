<?php

namespace Tests\Unit;

use Tests\TestCase;

/** Contract fixture for the current production Google Sheets headers.
 * This intentionally does not mutate or contact production; it documents the
 * fields BaseSheetRepository cannot durably persist until an approved schema
 * migration exists.
 */
class FinanceProductionSchemaContractTest extends TestCase
{
    public function test_current_production_headers_expose_financial_identity_but_drop_h819_metadata(): void
    {
        $headers = [
            'FINANCE_PAYMENT' => ['Payment_ID','Invoice_ID','Student_ID','Amount_Paid','Payment_Date','Payment_Method','Reference_Number','Proof_Image','Status','Verified_By','Verified_At','Notes','Created_At','Updated_At'],
            'FINANCE_INVOICE' => ['Invoice_ID','Student_ID','Period','Amount','Description','Status','Due_Date','Created_At','Updated_At','Invoice_Type','Company_ID','Category'],
            'FINANCE_TRANSACTION' => ['Transaction_ID','Transaction_Date','Account_ID','Type','Category','Amount','Reference_Type','Reference_ID','Description','Is_Active','Created_By','Created_At','Updated_At'],
            'MASTER_NOTIFICATION' => ['Notification_ID','User_ID','Title','Message','Is_Read','Link','Created_At','Updated_At'],
        ];
        $requiredIdentity = [
            'FINANCE_PAYMENT' => ['Payment_ID','Invoice_ID','Amount_Paid','Status'],
            'FINANCE_INVOICE' => ['Invoice_ID','Amount','Status'],
            'FINANCE_TRANSACTION' => ['Transaction_ID','Reference_Type','Reference_ID','Type','Amount'],
        ];
        foreach ($requiredIdentity as $sheet => $fields) {
            foreach ($fields as $field) {
                $this->assertContains($field, $headers[$sheet]);
            }
        }
        $this->assertNotContains('Idempotency_Key', $headers['FINANCE_PAYMENT']);
        $this->assertNotContains('Receipt_Number', $headers['FINANCE_PAYMENT']);
        $this->assertNotContains('Payment_Type', $headers['FINANCE_PAYMENT']);
        $this->assertNotContains('Is_Active', $headers['FINANCE_PAYMENT']);
        $this->assertNotContains('Created_By', $headers['FINANCE_PAYMENT']);
        $this->assertNotContains('Updated_By', $headers['FINANCE_PAYMENT']);
        $this->assertNotContains('Line_Items', $headers['FINANCE_INVOICE']);
        $this->assertNotContains('Updated_By', $headers['FINANCE_INVOICE']);
        $this->assertNotContains('Reference_Type', $headers['MASTER_NOTIFICATION']);
        $this->assertNotContains('Action_URL', $headers['MASTER_NOTIFICATION']);

        $missing = [];
        foreach (config('finance.schema') as $sheet => $required) {
            foreach ($required as $field) {
                if (!in_array($field, $headers[$sheet], true)) {
                    $missing[] = "{$sheet}.{$field}";
                }
            }
        }
        $this->assertSame([
            'FINANCE_PAYMENT.Created_By',
            'FINANCE_PAYMENT.Updated_By',
            'FINANCE_PAYMENT.Idempotency_Key',
            'FINANCE_PAYMENT.Idempotency_Fingerprint',
            'FINANCE_PAYMENT.Receipt_Number',
            'FINANCE_PAYMENT.Payment_Type',
            'FINANCE_PAYMENT.Is_Active',
            'FINANCE_INVOICE.Created_By',
            'FINANCE_INVOICE.Updated_By',
            'FINANCE_INVOICE.Line_Items',
            'FINANCE_INVOICE.Is_Active',
            'FINANCE_TRANSACTION.Updated_By',
            'MASTER_NOTIFICATION.Reference_Type',
            'MASTER_NOTIFICATION.Reference_ID',
        ], $missing);
    }
}
