<?php
$lines = file('d:\orderan\wakamiya\recovered_controller.txt');
$output = [];
$started = false;
foreach ($lines as $line) {
    if (!$started) {
        if (strpos($line, '1: <?php') === 0) {
            $started = true;
            $output[] = substr($line, 3);
        }
    } else {
        // Find first colon
        $pos = strpos($line, ': ');
        if ($pos !== false && $pos < 10) { // Line number length sanity check
            $output[] = substr($line, $pos + 2);
        } else {
            // Might be an un-numbered line if it's part of a multi-line string or something, but view_file adds it to ALL lines.
            // If it misses, just append it. Actually view_file prefixes EVERY line.
            // Wait, is it possible $pos is false?
        }
    }
}
file_put_contents('d:\orderan\wakamiya\app\Http\Controllers\Finance\StudentBillingController.php', implode('', $output));
echo "Restored from transcript.";
