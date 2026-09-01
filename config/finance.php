<?php

return [
    'accounts' => [
        // Explicit Account_ID or Account_Code. Empty means the service may
        // resolve only when exactly one named cash/bank asset exists.
        'default_id' => env('FINANCE_DEFAULT_ACCOUNT_ID'),
        'cash_id' => env('FINANCE_CASH_ACCOUNT_ID'),
        'bank_id' => env('FINANCE_BANK_ACCOUNT_ID'),
    ],

    /*
     * These are the columns that must exist before a finance-domain row may
     * be written.  Google Sheets has no database migration/transaction layer;
     * therefore a schema mismatch is a hard stop rather than an invitation to
     * drop fields that carry identity, audit, or reconciliation meaning.
     */
    'schema' => [
        'FINANCE_PAYMENT' => [
            'Payment_ID', 'Invoice_ID', 'Student_ID', 'Amount_Paid',
            'Payment_Date', 'Payment_Method', 'Reference_Number', 'Proof_Image',
            'Status', 'Verified_By', 'Verified_At', 'Notes',
            'Created_By', 'Created_At', 'Updated_By', 'Updated_At',
            'Idempotency_Key', 'Idempotency_Fingerprint', 'Receipt_Number',
            'Payment_Type', 'Is_Active',
        ],
        'FINANCE_INVOICE' => [
            'Invoice_ID', 'Student_ID', 'Period', 'Amount', 'Description',
            'Status', 'Due_Date', 'Created_By', 'Created_At', 'Updated_By',
            'Updated_At', 'Invoice_Type', 'Company_ID', 'Category',
            'Line_Items', 'Is_Active',
        ],
        'FINANCE_TRANSACTION' => [
            'Transaction_ID', 'Transaction_Date', 'Account_ID', 'Type',
            'Category', 'Amount', 'Reference_Type', 'Reference_ID',
            'Description', 'Is_Active', 'Created_By', 'Created_At',
            'Updated_By', 'Updated_At',
        ],
        'MASTER_NOTIFICATION' => [
            'Notification_ID', 'User_ID', 'Title', 'Message',
            'Reference_Type', 'Reference_ID', 'Created_At', 'Updated_At',
        ],
    ],
];
