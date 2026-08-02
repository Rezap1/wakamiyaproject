$user = app(\App\Services\Core\UserService::class)->getUserByEmail('lpkwakamiya01@gmail.com');
echo "Password in DB: " . $user['Password'] . "\n";
echo "Hash match for 'password': " . (Hash::check('password', $user['Password']) ? 'YES' : 'NO') . "\n";
