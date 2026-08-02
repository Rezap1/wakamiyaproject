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

        $userEmail = \Illuminate\Support\Facades\Auth::user()->email ?? 'user@example.com';
        $userRole = session('role') ?? 'GUEST';
        
        $notifications = $this->notificationService->getAll()
            ->filter(function($n) use ($userEmail, $userRole) {
                return (($n['User_ID'] ?? '') == $userEmail || ($n['Role'] ?? '') == $userRole) &&
                       ($n['Status'] ?? '') !== 'Archived';
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
