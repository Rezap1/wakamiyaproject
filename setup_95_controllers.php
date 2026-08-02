<?php
$dirCtrl = 'app/Http/Controllers/Core';
if(!is_dir($dirCtrl)) mkdir($dirCtrl, 0755, true);

// 1. Notification Controller
$notifCtrl = <<<'EOT'
<?php
namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Core\NotificationService;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function index(Request $request)
    {
        // Ideally fetch based on user role and id
        $userEmail = Auth::user()->email ?? 'user@example.com';
        $userRole = session('role') ?? 'GUEST';
        
        $notifications = $this->notificationService->getAll()
            ->filter(function($n) use ($userEmail, $userRole) {
                return (($n['User_ID'] ?? '') == $userEmail || ($n['Role'] ?? '') == $userRole) &&
                       ($n['Status'] ?? '') !== 'Archived';
            })->sortByDesc('Created_At');

        return view('notifications.index', compact('notifications'));
    }

    public function show($id)
    {
        $notification = $this->notificationService->getById($id);
        if (!$notification) abort(404);
        
        // Mark as read when opened
        if (($notification['Is_Read'] ?? 'FALSE') === 'FALSE') {
            $this->notificationService->MarkAsRead($id);
        }

        return view('notifications.show', compact('notification'));
    }

    public function markRead($id)
    {
        $this->notificationService->MarkAsRead($id);
        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllRead()
    {
        $userEmail = Auth::user()->email ?? 'user@example.com';
        $this->notificationService->MarkAllRead($userEmail);
        return back()->with('success', 'All notifications marked as read.');
    }

    public function archive($id)
    {
        $this->notificationService->ArchiveNotification($id);
        return redirect()->route('notifications.index')->with('success', 'Notification archived.');
    }

    public function destroy($id)
    {
        $this->notificationService->DeleteNotification($id);
        return redirect()->route('notifications.index')->with('success', 'Notification deleted.');
    }
}
EOT;
file_put_contents("$dirCtrl/NotificationController.php", $notifCtrl);

// 2. Add Routes
$fileRoute = 'routes/web.php';
$routeContent = file_get_contents($fileRoute);

if (strpos($routeContent, 'NotificationController') === false) {
    $useStatement = "use App\Http\Controllers\Core\NotificationController;";
    $routeContent = preg_replace('/use Illuminate\\\\Support\\\\Facades\\\\Route;/', "use Illuminate\Support\Facades\Route;\n" . $useStatement, $routeContent, 1);

    $routes = <<<'EOT'
        // Notification Routes
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.markAllRead');
        Route::get('/notifications/{id}', [NotificationController::class, 'show'])->name('notifications.show');
        Route::post('/notifications/{id}/mark-read', [NotificationController::class, 'markRead'])->name('notifications.markRead');
        Route::post('/notifications/{id}/archive', [NotificationController::class, 'archive'])->name('notifications.archive');
        Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
EOT;

    // Inject before the end of auth middleware group
    $routeContent = str_replace("Route::resource('employees', EmployeeController::class);", $routes . "\n        Route::resource('employees', EmployeeController::class);", $routeContent);
    file_put_contents($fileRoute, $routeContent);
    echo "Controllers and Routes created.\n";
} else {
    echo "Controllers created, Routes already exist.\n";
}
?>
