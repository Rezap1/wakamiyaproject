<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Interfaces\GoogleSheets\PayrollRepositoryInterface;

class UpdateHeaders extends Command
{
    protected $signature = 'sheet:update-headers';

    public function handle(PayrollRepositoryInterface $payrollRepo)
    {
        try {
            // Because $service is protected, I will use reflection or just make a public method on a temporary class
            // Let's just use Reflection
            $reflection = new \ReflectionClass($payrollRepo);
            
            $serviceProp = $reflection->getProperty('service');
            $serviceProp->setAccessible(true);
            $service = $serviceProp->getValue($payrollRepo);
            
            $spreadsheetIdProp = $reflection->getProperty('spreadsheetId');
            $spreadsheetIdProp->setAccessible(true);
            $spreadsheetId = $spreadsheetIdProp->getValue($payrollRepo);
            
            $sheetNameProp = $reflection->getProperty('sheetName');
            $sheetNameProp->setAccessible(true);
            $sheetName = $sheetNameProp->getValue($payrollRepo);
            
            $response = $service->spreadsheets_values->get($spreadsheetId, $sheetName . '!1:1');
            $headers = $response->getValues()[0];
            
            $missing = ['Created_At', 'Approved_Date', 'Paid_Date', 'Payment_Proof', 'Notes', 'Generated_Document', 'Document_Number', 'Payroll_Period'];
            $updated = false;
            foreach ($missing as $col) {
                if (!in_array($col, $headers)) {
                    $headers[] = $col;
                    $updated = true;
                }
            }
            
            if ($updated) {
                $body = new \Google_Service_Sheets_ValueRange([
                    'values' => [$headers]
                ]);
                $params = ['valueInputOption' => 'USER_ENTERED'];
                $service->spreadsheets_values->update($spreadsheetId, $sheetName . '!1:1', $body, $params);
                echo "Headers updated successfully.\n";
                $payrollRepo->clearCache();
            } else {
                echo "Headers already up to date.\n";
            }
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }
}
