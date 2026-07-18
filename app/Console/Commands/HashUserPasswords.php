<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Core\UserService;
use Illuminate\Support\Facades\Log;

class HashUserPasswords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:hash-passwords';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate plain text passwords in Google Sheets to Bcrypt hashes';

    /**
     * Execute the console command.
     */
    public function handle(UserService $userService)
    {
        $this->info('Memulai migrasi password...');
        
        $users = $userService->getAllUsers();
        $migratedCount = 0;

        foreach ($users as $user) {
            $password = $user['Password'] ?? '';
            
            // Mengecek apakah password BUKAN format bcrypt ($2y$)
            if (!empty($password) && !str_starts_with($password, '$2y$')) {
                $this->info("Hashing password untuk user: {$user['User_ID']} ({$user['Email']})");
                
                try {
                    // Update user. UserService akan otomatis melakukan Hash::make() pada password yang diberikan
                    $userService->updateUser($user['User_ID'], [
                        'Password' => $password
                    ]);
                    $migratedCount++;
                } catch (\Exception $e) {
                    $this->error("Gagal mengupdate user {$user['User_ID']}: " . $e->getMessage());
                    Log::error("Password migration failed for {$user['User_ID']}", ['error' => $e->getMessage()]);
                }
            }
        }

        $this->info("Migrasi selesai. Total password yang dienkripsi (bcrypt): {$migratedCount}");
        return 0;
    }
}
