<?php
$userRepo = app(\App\Interfaces\GoogleSheets\UserRepositoryInterface::class);
$users = collect($userRepo->fetchAll());
echo "All Users: \n";
foreach ($users as $u) {
    echo $u['User_ID'] . ' -> ' . ($u['Full_Name'] ?? 'no name') . "\n";
}
