<?php
$payrollRepo = app(\App\Interfaces\GoogleSheets\PayrollRepositoryInterface::class);
$response = $payrollRepo->service->spreadsheets_values->get($payrollRepo->spreadsheetId, 'MASTER_PAYROLL!1:1');
print_r($response->getValues()[0]);
