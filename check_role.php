<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$roles = app(\App\Services\Core\RoleService::class)->getAllRoles();
print_r(collect($roles)->where('Role_ID', 'ROL000008')->first());
