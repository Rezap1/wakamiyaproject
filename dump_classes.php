<?php
$classService = app(App\Services\Core\ClassService::class);
$allClasses = $classService->getAllClasses();
echo "Type: " . get_class($allClasses) . "\n";
echo "Count: " . $allClasses->count() . "\n";

echo "\n--- Raw data ---\n";
foreach($allClasses as $key => $c) {
    echo "Key: [$key] | ID: " . ($c['Class_ID'] ?? 'null') . " | Name: " . ($c['Class_Name'] ?? 'null') . " | Is_Active raw: [" . ($c['Is_Active'] ?? 'null') . "] | strlen: " . strlen($c['Is_Active'] ?? '') . "\n";
}

echo "\n--- Filter where('Is_Active', 'TRUE') ---\n";
$filtered = $allClasses->where('Is_Active', 'TRUE');
echo "Filtered count: " . $filtered->count() . "\n";

echo "\n--- Filter where('Is_Active', true) ---\n";
$filtered2 = $allClasses->where('Is_Active', true);
echo "Filtered2 count: " . $filtered2->count() . "\n";

echo "\n--- Filter filter(fn) ---\n";
$filtered3 = $allClasses->filter(function($c) {
    return strtoupper(trim($c['Is_Active'] ?? '')) === 'TRUE';
});
echo "Filtered3 count: " . $filtered3->count() . "\n";

echo "\n--- collect() wrap then where ---\n";
$rewrapped = collect($allClasses)->where('Is_Active', 'TRUE');
echo "Rewrapped count: " . $rewrapped->count() . "\n";
