<?php
namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Core\NotificationService;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    use \App\Traits\Exportable;

    protected $exportDateField = 'Created_At';

        protected function getExportConfig(\Illuminate\Http\Request $request)
    {

        $user = Auth::user();
        $notifications = $this->notificationService->getAll()
            ->filter(function($n) use ($user) {
                return $this->notificationService->isForUser($n, $user) &&
                       strtolower(trim($n['Status'] ?? '')) !== 'archived';
            })->sortByDesc('Created_At');

        return [
            'moduleName' => 'Notifikasi (Notification)',
            'data' => collect(array_values($notifications->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['Tanggal', 'Judul', 'Pesan', 'Status'],
            'mapRow' => function($row) {

                return [
                    isset($row['Created_At']) ? \Carbon\Carbon::parse($row['Created_At'])->format('d M Y H:i:s') : '-',
                    $row['Title'] ?? '-',
                    $row['Message'] ?? '-',
                    $row['Status'] ?? '-'
                ];
                    },
            'isLandscape' => true,
            'summary' => '<tr><td>Total Data</td><td>: '.$notifications->count().'</td></tr>'
        ];
    }

    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $notifications = $this->notificationService->getAll()
            ->filter(function($n) use ($user) {
                return $this->notificationService->isForUser($n, $user) &&
                       strtolower(trim($n['Status'] ?? '')) !== 'archived';
            })->sortByDesc(function($n) {
                try {
                    return \Carbon\Carbon::parse($n['Created_At'] ?? null)->timestamp;
                } catch (\Exception $e) {
                    return 0;
                }
            });

        $notifications = \App\Helpers\CollectionHelper::paginate($notifications, 15)->withQueryString();

        return view('notifications.index', compact('notifications'));
    }

    public function show($id)
    {
        $notification = $this->notificationService->visibleToCurrentUser($id);
        if (!$notification) abort(404, 'Notifikasi tidak ditemukan.');

        return view('notifications.show', compact('notification'));
    }

    public function readAndRedirect($id)
    {
        $notification = $this->notificationService->visibleToCurrentUser($id);
        if (!$notification) {
            return redirect()->route('notifications.index');
        }

        if (strtoupper(trim($notification['Is_Read'] ?? 'FALSE')) !== 'TRUE') {
            $this->notificationService->MarkAsRead($id);
        }

        $actionUrl = $notification['Action_URL'] ?? $notification['Url'] ?? null;
        $safeActionUrl = $this->safeActionUrl($actionUrl);
        if ($safeActionUrl) {
            return redirect($safeActionUrl);
        }

        return redirect()->route('notifications.show', $id);
    }

    public function markRead($id)
    {
        if (!$this->notificationService->MarkAsRead($id)) {
            abort(404, 'Notifikasi tidak ditemukan.');
        }
        return back()->with('success', 'Notifikasi berhasil ditandai telah dibaca.');
    }

    public function markAllRead()
    {
        $this->notificationService->MarkAllRead();
        return back()->with('success', 'Semua notifikasi berhasil ditandai telah dibaca.');
    }

    public function archive($id)
    {
        if (!$this->notificationService->ArchiveNotification($id)) {
            abort(404, 'Notifikasi tidak ditemukan.');
        }
        return redirect()->route('notifications.index')->with('success', 'Notifikasi berhasil diarsipkan.');
    }

    public function destroy($id)
    {
        if (!$this->notificationService->DeleteNotification($id)) {
            abort(404, 'Notifikasi tidak ditemukan.');
        }
        return redirect()->route('notifications.index')->with('success', 'Notifikasi berhasil dihapus.');
    }

    private function safeActionUrl(?string $actionUrl): ?string
    {
        $actionUrl = trim((string) $actionUrl);
        if ($actionUrl === '' || $actionUrl === '#') {
            return null;
        }

        if (str_starts_with($actionUrl, '/')) {
            return $actionUrl;
        }

        $appHost = parse_url(config('app.url'), PHP_URL_HOST);
        $actionHost = parse_url($actionUrl, PHP_URL_HOST);

        if ($appHost && $actionHost && strcasecmp($appHost, $actionHost) === 0) {
            return $actionUrl;
        }

        return null;
    }
}
