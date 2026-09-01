<?php
$logFile = 'C:\Users\user\.gemini\antigravity-ide\brain\6bd4db82-9e02-4b77-891c-70a8864db2f2\.system_generated\logs\transcript_full.jsonl';
$handle = fopen($logFile, 'r');
$bestMatch = '';
$maxLength = 0;

if ($handle) {
    while (($line = fgets($handle)) !== false) {
        if (strpos($line, 'StudentBillingController.php') !== false && strpos($line, 'downloadInvoicePdf') !== false && strpos($line, 'downloadPaymentProof') !== false) {
            $data = json_decode($line, true);
            if (isset($data['content']) && is_string($data['content'])) {
                if (strlen($data['content']) > $maxLength) {
                    $maxLength = strlen($data['content']);
                    $bestMatch = $data['content'];
                }
            }
        }
    }
    fclose($handle);
}

if ($bestMatch) {
    file_put_contents('d:\orderan\wakamiya\recovered_controller.txt', $bestMatch);
    echo "Recovered! Size: " . strlen($bestMatch);
} else {
    echo "Not found in logs.";
}
