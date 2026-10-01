<?php

namespace App\Http\Controllers\Core;

use App\Helpers\CollectionHelper;
use App\Http\Controllers\Controller;
use App\Services\Core\NotificationService;
use App\Services\Finance\StudentBillingNotificationService;
use App\Traits\Exportable;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    use Exportable;

    protected $exportDateField = 'Created_At';

    protected function getExportConfig(Request $request)
    {

        $user = Auth::user();
        $notifications = $this->notificationService->getAll()
            ->filter(function ($n) use ($user) {
                return $this->notificationService->isForUser($n, $user) &&
                       strtolower(trim($n['Status'] ?? '')) !== 'archived';
            })->sortByDesc('Created_At');

        return [
            'moduleName' => 'Notifikasi (Notification)',
            'data' => collect(array_values($notifications->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['Tanggal', 'Judul', 'Pesan', 'Status'],
            'mapRow' => function ($row) {

                return [
                    isset($row['Created_At']) ? Carbon::parse($row['Created_At'])->format('d M Y H:i:s') : '-',
                    $row['Title'] ?? '-',
                    $row['Message'] ?? '-',
                    $row['Status'] ?? '-',
                ];
            },
            'isLandscape' => true,
            'summary' => '<tr><td>Total Data</td><td>: '.$notifications->count().'</td></tr>',
        ];
    }

    protected $notificationService;

    protected $billingNotificationService;

    public function __construct(
        NotificationService $notificationService,
        ?StudentBillingNotificationService $billingNotificationService = null
    ) {
        $this->notificationService = $notificationService;
        $this->billingNotificationService = $billingNotificationService;
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $notifications = $this->notificationService->getAll()
            ->filter(function ($n) use ($user) {
                return $this->notificationService->isForUser($n, $user) &&
                       strtolower(trim($n['Status'] ?? '')) !== 'archived';
            })->sortByDesc(function ($n) {
                try {
                    return Carbon::parse($n['Created_At'] ?? null)->timestamp;
                } catch (\Exception $e) {
                    return 0;
                }
            });

        $notifications = CollectionHelper::paginate($notifications, 15)->withQueryString();
        try {
            $billingContexts = ($this->billingNotificationService
                ?? app(StudentBillingNotificationService::class))->contextsForNotifications($notifications->items());
        } catch (\Throwable) {
            $billingContexts = [];
        }

        return view('notifications.index', compact('notifications', 'billingContexts'));
    }

    public function show($id)
    {
        $notification = $this->ownedNotificationOrFail($id);
        $billingContext = ($this->billingNotificationService
            ?? app(StudentBillingNotificationService::class))->contextForNotification($notification);

        return view('notifications.show', compact('notification', 'billingContext'));
    }

    public function readAndRedirect($id)
    {
        $notification = $this->ownedNotificationOrFail($id);

        if (strtoupper(trim($notification['Is_Read'] ?? 'FALSE')) !== 'TRUE') {
            $this->notificationService->MarkAsRead($id);
        }

        $actionUrl = $notification['Link'] ?? $notification['Action_URL'] ?? $notification['Url'] ?? null;
        $safeActionUrl = $this->safeActionUrl($actionUrl);
        if ($safeActionUrl) {
            return redirect($safeActionUrl);
        }

        return redirect()->route('notifications.show', $id);
    }

    public function markRead(Request $request, $id)
    {
        $this->ownedNotificationOrFail($id);
        if (! $this->notificationService->MarkAsRead($id)) {
            abort(403, 'Anda tidak berhak mengubah notifikasi ini.');
        }
        if ($request->expectsJson()) {
            return response()->json(['status' => 'read']);
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
        $this->ownedNotificationOrFail($id);
        if (! $this->notificationService->ArchiveNotification($id)) {
            abort(403, 'Anda tidak berhak mengubah notifikasi ini.');
        }

        return redirect()->route('notifications.index')->with('success', 'Notifikasi berhasil diarsipkan.');
    }

    public function destroy($id)
    {
        $this->ownedNotificationOrFail($id);
        if (! $this->notificationService->DeleteNotification($id)) {
            abort(403, 'Anda tidak berhak mengubah notifikasi ini.');
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

    private function ownedNotificationOrFail(string $id): array
    {
        $notification = $this->notificationService->getById($id);
        if (! $notification) {
            abort(404, 'Notifikasi tidak ditemukan.');
        }
        if (! $this->notificationService->isForUser($notification, Auth::user())) {
            abort(403, 'Anda tidak berhak mengakses notifikasi ini.');
        }

        return $notification;
    }
}
