<?php

namespace App\Http\Controllers\Hr;

use App\Exceptions\EmployeeAttendanceRejectedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ScanQRAttendanceRequest;
use App\Http\Requests\StoreQRSessionRequest;
use App\Interfaces\GoogleSheets\EmployeeRepositoryInterface;
use App\Services\HR\QRAttendanceService;
use App\Support\Attendance\EmployeeAttendanceIncident;
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
            return back()->withErrors([
                'error' => $this->safeExceptionMessage($e, 'Sesi presensi QR tidak dapat dibuat.'),
            ])->withInput();
        }
    }

    public function displaySession($sessionId)
    {
        $session = $this->qrService->getSession($sessionId);
        if (! $session) {
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
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $this->safeExceptionMessage($e, 'Token QR tidak dapat dibuat.'),
            ], 400);
        }
    }

    public function scanner()
    {
        $user = auth()->user();
        $employeeRepo = app(EmployeeRepositoryInterface::class);
        $employee = collect($employeeRepo->fetchAll())->firstWhere('User_ID', $user->User_ID ?? '');

        return view('hr.attendance.qr_scanner', compact('user', 'employee'));
    }

    public function scan(ScanQRAttendanceRequest $request)
    {
        try {
            $validated = $request->validated();
            $token = $validated['token'];
            $deviceInfo = $validated['device_info'] ?? null;
            $lat = (float) $validated['latitude'];
            $lon = (float) $validated['longitude'];

            $result = $this->qrService->processScan($token, $deviceInfo, $lat, $lon);
            $statusLabel = ($result['status'] === 'PRESENT')
                ? 'Tepat Waktu'
                : 'Terlambat '.($result['late_minutes'] ?? 0).' Menit';

            return response()->json([
                'success' => true,
                'status' => 'recorded',
                'reason_code' => null,
                'message' => "Presensi Berhasil Dicatat! ({$statusLabel})",
                'data' => $result,
            ]);
        } catch (EmployeeAttendanceRejectedException $e) {
            $reportId = $e->httpStatus >= 500
                ? EmployeeAttendanceIncident::report($request, $e, 'Employee QR attendance operational rejection')
                : null;

            return response()->json([
                'success' => false,
                'status' => 'rejected',
                'reason_code' => $e->reasonCode,
                'message' => $reportId ? $e->userMessage." Kode laporan: {$reportId}." : $e->userMessage,
                'report_id' => $reportId,
                'data' => $e->data ?: null,
            ], $e->httpStatus)->withHeaders(array_filter(['X-Request-ID' => $reportId]));
        } catch (\Throwable $e) {
            $reportId = EmployeeAttendanceIncident::report($request, $e, 'Unexpected employee QR attendance failure');

            return response()->json([
                'success' => false,
                'status' => 'error',
                'reason_code' => 'SYSTEM_ERROR',
                'message' => "Presensi belum dapat diproses karena terjadi gangguan sistem. Kode laporan: {$reportId}.",
                'report_id' => $reportId,
                'data' => null,
            ], 500)->header('X-Request-ID', $reportId);
        }
    }

    public function closeSession($sessionId)
    {
        try {
            $this->qrService->closeSession($sessionId);

            return redirect()->route('hr.attendance.qr.index')->with('success', "Sesi presensi QR #{$sessionId} telah ditutup.");
        } catch (\Exception $e) {
            return back()->withErrors([
                'error' => $this->safeExceptionMessage($e, 'Sesi presensi QR tidak dapat ditutup.'),
            ]);
        }
    }

    public function sessionSummary($sessionId)
    {
        try {
            $summary = $this->qrService->getSessionSummary($sessionId);

            return response()->json([
                'success' => true,
                'data' => $summary,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $this->safeExceptionMessage($e, 'Ringkasan sesi QR tidak dapat dimuat.'),
            ], 400);
        }
    }
}
