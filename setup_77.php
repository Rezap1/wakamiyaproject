<?php
$baseDir = __DIR__;

// 1. NotificationRepositoryInterface
$interface = <<<PHP
<?php
namespace App\Interfaces\GoogleSheets;

interface NotificationRepositoryInterface
{
    public function fetchAll();
    public function findById(\$id);
    public function getByUserId(\$userId);
    public function create(array \$data);
    public function update(\$id, array \$data);
}
PHP;
@mkdir("$baseDir/app/Interfaces/GoogleSheets", 0777, true);
file_put_contents("$baseDir/app/Interfaces/GoogleSheets/NotificationRepositoryInterface.php", $interface);

// 2. NotificationGoogleSheetRepository
$repo = <<<PHP
<?php
namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\NotificationRepositoryInterface;

class NotificationGoogleSheetRepository extends AbstractGoogleSheetRepository implements NotificationRepositoryInterface
{
    protected string \$range = 'MASTER_NOTIFICATION!A:S';

    protected function mapRowToAssoc(array \$row): array
    {
        return [
            'Notification_ID' => \$row[0] ?? '',
            'User_ID' => \$row[1] ?? '',
            'Role' => \$row[2] ?? '',
            'Module' => \$row[3] ?? '',
            'Reference_ID' => \$row[4] ?? '',
            'Category' => \$row[5] ?? '',
            'Priority' => \$row[6] ?? '',
            'Title' => \$row[7] ?? '',
            'Message' => \$row[8] ?? '',
            'Action_URL' => \$row[9] ?? '',
            'Icon' => \$row[10] ?? '',
            'Color' => \$row[11] ?? '',
            'Is_Read' => \$row[12] ?? 'FALSE',
            'Read_At' => \$row[13] ?? '',
            'Is_Archived' => \$row[14] ?? 'FALSE',
            'Archived_At' => \$row[15] ?? '',
            'Created_At' => \$row[16] ?? '',
            'Created_By' => \$row[17] ?? '',
            'Notes' => \$row[18] ?? '',
        ];
    }

    public function getByUserId(\$userId)
    {
        \$all = \$this->fetchAll();
        \$result = [];
        foreach (\$all as \$n) {
            if (isset(\$n['User_ID']) && \$n['User_ID'] == \$userId) {
                \$result[] = \$n;
            }
        }
        return collect(\$result)->sortByDesc('Created_At')->values()->all();
    }

    public function create(array \$data)
    {
        // Mock appending row logic or implement real Google Sheet append here
        // We will just do a placeholder that works nicely if GSheet is set up
        \$id = 'NOTIF-' . time() . rand(100, 999);
        \$row = [
            \$id,
            \$data['User_ID'] ?? '',
            \$data['Role'] ?? '',
            \$data['Module'] ?? '',
            \$data['Reference_ID'] ?? '',
            \$data['Category'] ?? '',
            \$data['Priority'] ?? 'Low',
            \$data['Title'] ?? '',
            \$data['Message'] ?? '',
            \$data['Action_URL'] ?? '',
            \$data['Icon'] ?? '',
            \$data['Color'] ?? '',
            'FALSE',
            '',
            'FALSE',
            '',
            date('Y-m-d H:i:s'),
            \$data['Created_By'] ?? 'System',
            \$data['Notes'] ?? ''
        ];
        \$this->appendRow('MASTER_NOTIFICATION!A:S', \$row);
        \$this->clearCache();
        return \$id;
    }

    public function update(\$id, array \$data)
    {
        \$all = \$this->fetchAll();
        \$rowIndex = -1;
        \$found = null;
        
        foreach (\$all as \$i => \$row) {
            if ((\$row['Notification_ID'] ?? '') === \$id) {
                // Header is row 1, data starts at 2
                \$rowIndex = \$i + 2;
                \$found = \$row;
                break;
            }
        }

        if (\$rowIndex !== -1 && \$found) {
            // Update fields
            \$updatedRow = [
                \$id,
                \$data['User_ID'] ?? \$found['User_ID'],
                \$data['Role'] ?? \$found['Role'],
                \$data['Module'] ?? \$found['Module'],
                \$data['Reference_ID'] ?? \$found['Reference_ID'],
                \$data['Category'] ?? \$found['Category'],
                \$data['Priority'] ?? \$found['Priority'],
                \$data['Title'] ?? \$found['Title'],
                \$data['Message'] ?? \$found['Message'],
                \$data['Action_URL'] ?? \$found['Action_URL'],
                \$data['Icon'] ?? \$found['Icon'],
                \$data['Color'] ?? \$found['Color'],
                \$data['Is_Read'] ?? \$found['Is_Read'],
                \$data['Read_At'] ?? \$found['Read_At'],
                \$data['Is_Archived'] ?? \$found['Is_Archived'],
                \$data['Archived_At'] ?? \$found['Archived_At'],
                \$data['Created_At'] ?? \$found['Created_At'],
                \$data['Created_By'] ?? \$found['Created_By'],
                \$data['Notes'] ?? \$found['Notes'],
            ];

            // In real app, call updateRow. For now we use the abstract class method.
            \$this->updateRow("MASTER_NOTIFICATION!A{\$rowIndex}:S{\$rowIndex}", [\$updatedRow]);
            \$this->clearCache();
            return true;
        }
        return false;
    }
}
PHP;
@mkdir("$baseDir/app/Repositories/GoogleSheets", 0777, true);
file_put_contents("$baseDir/app/Repositories/GoogleSheets/NotificationGoogleSheetRepository.php", $repo);

// 3. NotificationService
$service = <<<PHP
<?php
namespace App\Services\Core;

use App\Interfaces\GoogleSheets\NotificationRepositoryInterface;

class NotificationService
{
    protected \$repo;

    public function __construct(NotificationRepositoryInterface \$repo)
    {
        \$this->repo = \$repo;
    }

    public function send(\$userId, \$title, \$message, \$actionUrl = '#', \$category = 'System', \$priority = 'Low', \$color = 'blue', \$module = '', \$referenceId = '')
    {
        \$data = [
            'User_ID' => \$userId,
            'Title' => \$title,
            'Message' => \$message,
            'Action_URL' => \$actionUrl,
            'Category' => \$category,
            'Priority' => \$priority,
            'Color' => \$color,
            'Module' => \$module,
            'Reference_ID' => \$referenceId,
            'Created_By' => auth()->user()->Username ?? 'System',
        ];
        return \$this->repo->create(\$data);
    }

    public function getMyNotifications(\$userId)
    {
        return \$this->repo->getByUserId(\$userId);
    }

    public function getSystemNotifications()
    {
        return collect(\$this->repo->fetchAll())->sortByDesc('Created_At')->values()->all();
    }

    public function markAsRead(\$notificationId)
    {
        return \$this->repo->update(\$notificationId, [
            'Is_Read' => 'TRUE',
            'Read_At' => date('Y-m-d H:i:s')
        ]);
    }

    public function markAllAsRead(\$userId)
    {
        \$notifs = \$this->getMyNotifications(\$userId);
        foreach (\$notifs as \$n) {
            if ((\$n['Is_Read'] ?? 'FALSE') === 'FALSE') {
                \$this->repo->update(\$n['Notification_ID'], [
                    'Is_Read' => 'TRUE',
                    'Read_At' => date('Y-m-d H:i:s')
                ]);
            }
        }
    }

    public function archive(\$notificationId)
    {
        return \$this->repo->update(\$notificationId, [
            'Is_Archived' => 'TRUE',
            'Archived_At' => date('Y-m-d H:i:s')
        ]);
    }
}
PHP;
@mkdir("$baseDir/app/Services/Core", 0777, true);
file_put_contents("$baseDir/app/Services/Core/NotificationService.php", $service);

echo "Notification setup created.\\n";
