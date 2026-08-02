<?php
$validations = [
    'Teacher' => ['User_ID', '\App\Services\Core\UserService::class', 'getUserById'],
    'Attendance' => ['Schedule_ID', '\App\Services\Academic\ScheduleService::class', 'getById'],
    'Score' => ['Assessment_ID', '\App\Services\Academic\AssessmentService::class', 'getById'],
];

foreach ($validations as $model => $data) {
    $field = $data[0];
    $service = $data[1];
    $method = $data[2];
    
    $reqFile = "app/Http/Requests/Store{$model}Request.php";
    if (file_exists($reqFile)) {
        $content = file_get_contents($reqFile);
        
        $injection = "
            '$field' => [
                'required',
                function (\$attribute, \$value, \$fail) {
                    \$service = app($service);
                    \$parent = \$service->$method(\$value);
                    if (!\$parent) {
                        \$fail('Parent data $field tidak ditemukan.');
                    }
                }
            ],
        ";
        
        if (strpos($content, "'$field'") === false) {
            $content = str_replace('return [', "return [\n$injection", $content);
            file_put_contents($reqFile, $content);
            echo "Updated $reqFile\n";
        }
    }
}
