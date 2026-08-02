$userSvc = app(\App\Services\Core\UserService::class);
$userSvc->updateUser('USR000001', [
    'Role_ID' => 'ROL000002',
    'Password' => 'password'
]);
echo "Updated Deri Alamsah to Administrator and reset password to 'password'.\n";
