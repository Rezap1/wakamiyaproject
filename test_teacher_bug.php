<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$userService = app(\App\Services\Core\UserService::class);
$teacherService = app(\App\Services\Core\TeacherService::class);
$classService = app(\App\Services\Core\ClassService::class);

echo "1. Creating User with TEACHER role...\n";
$roleRepo = app(\App\Interfaces\GoogleSheets\RoleRepositoryInterface::class);
$teacherRole = collect($roleRepo->fetchAll())->firstWhere('Role_Name', 'TEACHER');

Auth::loginUsingId('USR000001');

$user = $userService->createUser([
    'Username' => 'testteacher' . rand(100, 999),
    'Password' => 'password123',
    'Full_Name' => 'Bug Test Teacher',
    'Email' => 'bugtest' . rand(100,999) . '@wakamiya.test',
    'Role_ID' => $teacherRole['Role_ID']
]);
echo "User created: " . $user['User_ID'] . "\n";

echo "2. Creating Teacher record manually (simulating HR/Academic flow)...\n";
$teacher = $teacherService->createTeacher([
    'User_ID' => $user['User_ID'],
    'Specialization' => 'Matematika',
    'Hire_Date' => '2026-08-01',
    'Teaching_Status' => 'TETAP'
]);
echo "Teacher created: " . $teacher['Teacher_ID'] . "\n";

echo "3. Testing ClassController logic...\n";
$teachers = $teacherService->getAllTeachers()->where('Is_Active', 'TRUE')->values();
$teacherFoundInDropdown = $teachers->firstWhere('Teacher_ID', $teacher['Teacher_ID']);

if ($teacherFoundInDropdown) {
    echo "Teacher IS in dropdown. Teacher_ID: " . $teacherFoundInDropdown['Teacher_ID'] . "\n";
    
    echo "4. Testing Class creation with this Teacher_ID...\n";
    // Need a Program and Batch
    $programService = app(\App\Services\Core\ProgramService::class);
    $batchService = app(\App\Services\Core\BatchService::class);
    
    $program = collect($programService->getAllPrograms())->first();
    $batch = collect($batchService->getAllBatches())->first();
    
    if (!$program || !$batch) {
        die("Need Program and Batch to test Class creation.\n");
    }

    try {
        $classData = [
            'Class_Code' => 'TEST-' . rand(100, 999),
            'Class_Name' => 'Test Class',
            'Program_ID' => $program['Program_ID'],
            'Batch_ID' => $batch['Batch_ID'],
            'Homeroom_Teacher_ID' => $teacher['Teacher_ID'],
            'Capacity' => 20
        ];
        
        $class = $classService->createClass($classData);
        echo "SUCCESS! Class created: " . $class['Class_ID'] . "\n";
    } catch (\Exception $e) {
        echo "FAILED to create Class: " . $e->getMessage() . "\n";
    }

} else {
    echo "Teacher is NOT in dropdown!\n";
}

echo "\nDone.\n";
