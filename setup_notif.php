<?php
$basePath = __DIR__;

// Interface
$interfaceContent = <<<PHP
<?php
namespace App\Interfaces\GoogleSheets;

interface NotificationRepositoryInterface
{
    public function fetchAll();
    public function findById(string \$id);
    public function generateNewId(string \$prefix, int \$padding = 6): string;
    public function create(array \$data);
    public function update(string \$id, array \$data);
    public function softDelete(string \$id);
}
PHP;
file_put_contents("$basePath/app/Interfaces/GoogleSheets/NotificationRepositoryInterface.php", $interfaceContent);

// Repository
$repoContent = <<<PHP
<?php
namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\NotificationRepositoryInterface;

class NotificationRepository extends BaseSheetRepository implements NotificationRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        \$this->sheetName = 'MASTER_NOTIFICATION';
        \$this->cacheKey = 'notifications_sheet';
        \$this->primaryKey = 'Notification_ID';
    }

    public function findById(string \$id)
    {
        return \$this->fetchAll()->firstWhere(\$this->primaryKey, \$id);
    }

    public function create(array \$data)
    {
        return \$this->append(\$data);
    }
    
    public function update(string \$id, array \$data)
    {
        return \$this->updateRow(\$id, \$data);
    }
    
    public function softDelete(string \$id)
    {
        // Notifications don't usually have Is_Active, we might just delete or mark deleted
        return \$this->updateRow(\$id, ['Is_Read' => 'TRUE']);
    }
}
PHP;
file_put_contents("$basePath/app/Repositories/GoogleSheets/NotificationRepository.php", $repoContent);

// Service
$serviceContent = <<<PHP
<?php
namespace App\Services\Core;

use App\Interfaces\GoogleSheets\NotificationRepositoryInterface;

class NotificationService
{
    protected \$repository;

    public function __construct(NotificationRepositoryInterface \$repository)
    {
        \$this->repository = \$repository;
    }

    public function getAll()
    {
        return \$this->repository->fetchAll();
    }

    public function getUnreadForUser(\$userId)
    {
        return \$this->getAll()->filter(function (\$item) use (\$userId) {
            return \$item['User_ID'] === \$userId && (\$item['Is_Read'] ?? 'FALSE') === 'FALSE';
        });
    }

    public function notifyUser(\$userId, \$title, \$message, \$link = null)
    {
        \$data = [
            'Notification_ID' => \$this->repository->generateNewId('NOTIF', 6),
            'User_ID' => \$userId,
            'Title' => \$title,
            'Message' => \$message,
            'Is_Read' => 'FALSE',
            'Link' => \$link,
            'Created_At' => now()->toDateTimeString()
        ];
        \$result = \$this->repository->create(\$data);
        \$this->repository->clearCache();
        return \$result;
    }

    public function markAsRead(\$notificationId)
    {
        \$result = \$this->repository->update(\$notificationId, ['Is_Read' => 'TRUE', 'Updated_At' => now()->toDateTimeString()]);
        \$this->repository->clearCache();
        return \$result;
    }
}
PHP;
file_put_contents("$basePath/app/Services/Core/NotificationService.php", $serviceContent);

echo "Notification foundation created.\n";
