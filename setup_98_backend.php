<?php
$dirInterface = 'app/Interfaces/GoogleSheets';
$dirRepo = 'app/Repositories/GoogleSheets';
$dirService = 'app/Services/Core';

// 1. Interface
$auditInterface = <<<'EOT'
<?php
namespace App\Interfaces\GoogleSheets;
interface AuditLogRepositoryInterface {
    public function getAll();
    public function getById($id);
    public function create(array $data);
}
EOT;
file_put_contents("$dirInterface/AuditLogRepositoryInterface.php", $auditInterface);

// 2. Repository
$auditRepo = <<<'EOT'
<?php
namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\AuditLogRepositoryInterface;

class AuditLogRepository extends BaseSheetRepository implements AuditLogRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_AUDIT_LOG';
        $this->cacheKey = 'audit_log_sheet';
        $this->primaryKey = 'Audit_ID';
    }

    public function getAll() { return $this->fetchAll(); }
    public function getById($id) { return $this->fetchAll()->firstWhere($this->primaryKey, $id); }
    public function create(array $data) { return $this->append($data); }
}
EOT;
file_put_contents("$dirRepo/AuditLogRepository.php", $auditRepo);

// 3. Service
$auditService = <<<'EOT'
<?php
namespace App\Services\Core;

use App\Interfaces\GoogleSheets\AuditLogRepositoryInterface;

class AuditLogService
{
    protected $repo;

    public function __construct(AuditLogRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function getAll() { return $this->repo->getAll(); }
    public function getById($id) { return $this->repo->getById($id); }

    public function log($module, $action, $referenceType, $referenceId, $oldValue = null, $newValue = null)
    {
        try {
            $user = auth()->user();
            $data = [
                'Audit_ID' => uniqid('AUD_'),
                'User_ID' => $user->email ?? ($user->User_ID ?? 'System'),
                'Role' => session('role') ?? 'System',
                'Department' => $user->Department ?? 'System',
                'Module' => $module,
                'Reference_Type' => $referenceType,
                'Reference_ID' => $referenceId,
                'Action' => $action,
                'Old_Value' => is_array($oldValue) ? json_encode($oldValue) : $oldValue,
                'New_Value' => is_array($newValue) ? json_encode($newValue) : $newValue,
                'IPAddress' => request()->ip() ?? '127.0.0.1',
                'Device' => request()->header('User-Agent'),
                'Browser' => $this->getBrowser(request()->header('User-Agent')),
                'Operating_System' => $this->getOS(request()->header('User-Agent')),
                'Location' => 'Local', // Can be expanded with geo-ip
                'Status' => 'Success',
                'Created_At' => now()->toDateTimeString()
            ];

            $res = $this->repo->create($data);
            $this->repo->clearCache();
            return $res;
        } catch (\Exception $e) {
            // Silently fail so audit logging doesn't crash the main transaction
            return false;
        }
    }

    public function activity() { return $this->getAll()->sortByDesc('Created_At'); }
    public function history() { return $this->activity(); }
    public function userHistory($userId) { return $this->activity()->where('User_ID', $userId); }
    public function moduleHistory($module) { return $this->activity()->where('Module', $module); }
    public function referenceHistory($refId) { return $this->activity()->where('Reference_ID', $refId); }
    public function today() { 
        $today = now()->format('Y-m-d');
        return $this->activity()->filter(function($log) use ($today) {
            return strpos($log['Created_At'] ?? '', $today) === 0;
        });
    }
    
    public function statistics() {
        $logs = $this->getAll();
        return [
            'total' => $logs->count(),
            'today' => $this->today()->count(),
            'top_modules' => $logs->groupBy('Module')->map->count()->sortDesc()->take(5),
            'top_users' => $logs->groupBy('User_ID')->map->count()->sortDesc()->take(5)
        ];
    }

    private function getBrowser($userAgent) {
        if(strpos($userAgent, 'Firefox') !== false) return 'Firefox';
        if(strpos($userAgent, 'Chrome') !== false) return 'Chrome';
        if(strpos($userAgent, 'Safari') !== false) return 'Safari';
        return 'Unknown Browser';
    }

    private function getOS($userAgent) {
        if(strpos($userAgent, 'Windows') !== false) return 'Windows';
        if(strpos($userAgent, 'Mac') !== false) return 'MacOS';
        if(strpos($userAgent, 'Linux') !== false) return 'Linux';
        return 'Unknown OS';
    }
}
EOT;
file_put_contents("$dirService/AuditLogService.php", $auditService);

echo "Audit Backend Created.\n";
?>
