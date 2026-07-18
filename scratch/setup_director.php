<?php
use App\Interfaces\GoogleSheets\UserRepositoryInterface;
use App\Interfaces\GoogleSheets\ModuleRepositoryInterface;
use App\Interfaces\GoogleSheets\PermissionRepositoryInterface;
use Illuminate\Support\Facades\Hash;

$userRepo = app(UserRepositoryInterface::class);
$user = $userRepo->fetchAll()->firstWhere('User_ID', 'USR000001');
if ($user) {
    $user['Role_ID'] = 'ROL000001'; // DIRECTOR
    $user['Password'] = Hash::make('Wakamiya2026!');
    $user['Username'] = 'deri.alamsah';
    $userRepo->update('USR000001', $user);
    echo "User USR000001 updated to Director (deri.alamsah).\n";
}

$moduleRepo = app(ModuleRepositoryInterface::class);
$modules = $moduleRepo->fetchAll();

$permRepo = app(PermissionRepositoryInterface::class);
// Delete existing permissions for ROL000001 to avoid duplicates
$existingPerms = $permRepo->fetchAll()->where('Role_ID', 'ROL000001');
foreach($existingPerms as $p) {
    // Delete by setting Is_Active to FALSE or hard delete if not supported
    $p['Is_Active'] = 'FALSE';
    $permRepo->update($p['Permission_ID'], $p);
}

// Recreate all permissions
foreach($modules as $m) {
    // Check if permission already exists after our mock delete
    $existing = $permRepo->findByRoleAndModule('ROL000001', $m['Module_ID']);
    if ($existing) {
        $existing['Is_Active'] = 'TRUE';
        $existing['Can_View'] = 'TRUE';
        $existing['Can_Create'] = 'TRUE';
        $existing['Can_Edit'] = 'TRUE';
        $existing['Can_Delete'] = 'TRUE';
        $existing['Can_Print'] = 'TRUE';
        $existing['Can_Export_PDF'] = 'TRUE';
        $permRepo->update($existing['Permission_ID'], $existing);
        echo "Permission updated for {$m['Module_Name']}\n";
    } else {
        $permissionId = $permRepo->generateNewId('PRM', 6);
        $newPermission = [
            'Permission_ID' => $permissionId,
            'Role_ID' => 'ROL000001',
            'Module_ID' => $m['Module_ID'],
            'Can_View' => 'TRUE',
            'Can_Create' => 'TRUE',
            'Can_Edit' => 'TRUE',
            'Can_Delete' => 'TRUE',
            'Can_Print' => 'TRUE',
            'Can_Export_PDF' => 'TRUE',
            'Is_Active' => 'TRUE',
            'Created_By' => 'SYSTEM',
            'Updated_By' => 'SYSTEM',
        ];
        $permRepo->create($newPermission);
        echo "Permission created for {$m['Module_Name']}\n";
    }
}
echo "Setup complete.\n";
