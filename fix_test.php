<?php
$content = file_get_contents('d:\orderan\wakamiya\tests\Unit\InvoiceControllerWorkflowTest.php');
$content = str_replace(
    '$this->assertTrue(session(\'errors\')->has(\'error\'));',
    '$this->assertTrue(session()->has(\'error\'));',
    $content
);
file_put_contents('d:\orderan\wakamiya\tests\Unit\InvoiceControllerWorkflowTest.php', $content);
echo "Fixed test.";
