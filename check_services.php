<?php
$files = glob('app/Services/Academic/*.php');
foreach($files as $file) {
    echo basename($file) . "\n";
}
