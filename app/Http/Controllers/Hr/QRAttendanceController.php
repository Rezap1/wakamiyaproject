<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\HR\QRAttendanceService;
use App\Http\Requests\StoreQRSessionRequest;
use App\Http\Requests\ScanQRAttendanceRequest;
use Illuminate\Support\Facades\Cache;

class QRAttendanceController extends Controller
{
    protected $qrService;

    public function __construct(QRAttendanceService $qrService)
    {
        $this->qrService = $qrService;
    }

    public function index()
    {
        $sessionsList = Cache::get('qr_sessions_list', []);
        $sessions = collect(array_values($sessionsList))->sortByDesc('Created_At')->values();
        
        return view('hr.attendance.qr_index', compact('sessions'));
    }

    public function storeSession(StoreQRSessionRequest $request)
    {
        try {
            $session = $this->qrService->createSession($request->validated());
            return redirect()->route('hr.attendance.qr.display', $session['Session_ID'])
                ->with('success', "Sesi presensi QR #{$session['Session_ID']} berhasil dibuka.");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function displaySession($sessionId)
    {
        $session = $this->qrService->getSession($sessionId);
        if (!$session) {
            return redirect()->route('hr.attendance.qr.index')->with('error', 'Sesi presensi QR tidak ditemukan.');
        }

        return view('hr.attendance.qr_display', compact('session'));
    }

    public function getDynamicToken($sessionId)
    {
        try {
            $data = $this->qrService->generateDynamicToken($sessionId);
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function scanner()
    {
        $user = auth()->user();
        $employeeRepo = app(\App\Interfaces\GoogleSheets\EmployeeRepositoryInterface::class);
        $employee = collect($employeeRepo->fetchAll())->firstWhere('User_ID', $user->User_ID ?? '');

        return view('hr.attendance.qr_scanner', compact('user', 'employee'));
    }

    public function scan(ScanQRAttendanceRequest $request)
    {
        try {
            $token = $request->input('token');
            $deviceInfo = $request->input('device_info');
            $lat = $request->filled('latitude') ? (float) $request->input('latitude') : null;
            $lon = $request->filled('longitude') ? (float) $request->input('longitude') : null;
            
            $result = $this->qrService->processScan($token, $deviceInfo, $lat, $lon);

            return response()->json([
                'success' => true,
                'message' => "Presensi Berhasil Recorded! (" . ($result['status'] === 'PRESENT' ? 'Tepat Waktu' : 'Terlambat ' . $result['late_minutes'] . ' Menit') . ")",
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function closeSession($sessionId)
    {
        try {
            $this->qrService->closeSession($sessionId);
            return redirect()->route('hr.attendance.qr.index')->with('success', "Sesi presensi QR #{$sessionId} telah ditutup.");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function sessionSummary($sessionId)
    {
        try {
            $summary = $this->qrService->getSessionSummary($sessionId);
            return response()->json([
                'success' => true,
                'data' => $summary
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
