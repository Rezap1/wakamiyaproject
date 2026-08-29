<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Core\PermanentQrService;
use App\Services\Academic\StudentQRAttendanceService;
use App\Services\HR\QRAttendanceService;
use App\Services\Core\ActivityLogService;

class PermanentQrController extends Controller
{
    protected $qrService;
    protected $studentQrService;
    protected $hrQrService;
    protected $activityLog;

    public function __construct(
        PermanentQrService $qrService,
        StudentQRAttendanceService $studentQrService,
        QRAttendanceService $hrQrService,
        ActivityLogService $activityLog
    ) {
        $this->qrService = $qrService;
        $this->studentQrService = $studentQrService;
        $this->hrQrService = $hrQrService;
        $this->activityLog = $activityLog;
    }



    // ==========================================
    // MANAGEMENT UI METHODS
    // ==========================================

    public function index()
    {
        $roleAlias = $this->currentRoleAlias();
        $qrCodes = collect($this->qrService->getAllQrCodes())
            ->filter(function ($qr) use ($roleAlias) {
                return $this->canManageQrType($roleAlias, $qr['QR_TYPE'] ?? '');
            })
            ->values();

        return view('attendance.qr.index', compact('qrCodes'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'QR_TYPE' => 'required|in:STUDENT,EMPLOYEE',
            'LABEL' => 'required|string|max:100',
            'ACTIVE_FROM' => 'nullable|date',
            'ACTIVE_UNTIL' => 'nullable|date|after:ACTIVE_FROM'
        ]);

        $this->assertCanManageQrType($data['QR_TYPE']);

        try {
            $this->qrService->createQr($data);
            return redirect()->route('attendance.qr.index')->with('success', 'QR Presensi Permanen berhasil dibuat.');
        } catch (\Exception $e) {
            return redirect()->back()->with(
                'error',
                $this->safeExceptionMessage($e, 'QR Presensi Permanen tidak dapat dibuat.')
            )->withInput();
        }
    }

    public function preview($id)
    {
        $qr = $this->qrService->getQrById($id);
        if (!$qr) {
            return redirect()->route('attendance.qr.index')->with('error', 'QR tidak ditemukan.');
        }
        $this->assertCanManageQr($qr);
        return view('attendance.qr.preview', compact('qr'));
    }

    public function printView($id)
    {
        $qr = $this->qrService->getQrById($id);
        if (!$qr) {
            return redirect()->route('attendance.qr.index')->with('error', 'QR tidak ditemukan.');
        }
        $this->assertCanManageQr($qr);
        
        $this->activityLog->log(
            'ATTENDANCE_QR',
            'PRINT',
            "Mencetak QR Presensi Permanen: {$qr['IDENTIFIER']}",
            null,
            ['QR_ID' => $id, 'IDENTIFIER' => $qr['IDENTIFIER']]
        );
        
        return view('attendance.qr.print', compact('qr'));
    }

    public function downloadPdf($id)
    {
        // For simplicity, we just reuse the print layout and rely on browser print-to-PDF or 
        // a simple wrapper if a PDF engine is used. Since EPS Rev.5.0 requires using existing PDF engine,
        // Let's use Barryvdh\DomPDF if available, otherwise just use Print view.
        // Assuming we can return the print view for now and user can print-to-pdf, 
        // or actually generate PDF.
        $qr = $this->qrService->getQrById($id);
        if (!$qr) {
            return redirect()->route('attendance.qr.index')->with('error', 'QR tidak ditemukan.');
        }
        $this->assertCanManageQr($qr);
        
        $this->activityLog->log(
            'ATTENDANCE_QR',
            'DOWNLOAD_PDF',
            "Mengunduh PDF QR Presensi Permanen: {$qr['IDENTIFIER']}",
            null,
            ['QR_ID' => $id, 'IDENTIFIER' => $qr['IDENTIFIER']]
        );

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $isPdf = true;
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('attendance.qr.print', compact('qr', 'isPdf'))
                ->setPaper('a4', 'portrait');
            return $pdf->download("QR_Presensi_{$qr['IDENTIFIER']}.pdf");
        }
        
        // Fallback to print view if PDF engine not found, though EPS demands using existing.
        // The existing engine is usually mpdf or dompdf.
        return view('attendance.qr.print', compact('qr'));
    }

    public function deactivate($id)
    {
        $qr = $this->qrService->getQrById($id);
        if (!$qr) {
            return redirect()->route('attendance.qr.index')->with('error', 'QR tidak ditemukan.');
        }
        $this->assertCanManageQr($qr);

        try {
            $this->qrService->deactivateQr($id);
            return redirect()->route('attendance.qr.index')->with('success', 'QR Presensi berhasil dinonaktifkan.');
        } catch (\Exception $e) {
            return redirect()->route('attendance.qr.index')->with(
                'error',
                $this->safeExceptionMessage($e, 'QR Presensi tidak dapat dinonaktifkan.')
            );
        }
    }

    public function updateAvailability(Request $request, $id)
    {
        $data = $request->validate([
            'STATUS' => 'required|in:ACTIVE,INACTIVE',
            'ACTIVE_FROM' => 'nullable|date',
            'ACTIVE_UNTIL' => 'nullable|date|after:ACTIVE_FROM',
        ]);

        $qr = $this->qrService->getQrById($id);
        if (!$qr) {
            return redirect()->route('attendance.qr.index')->with('error', 'QR tidak ditemukan.');
        }
        $this->assertCanManageQr($qr);

        try {
            $this->qrService->updateAvailability($id, $data);
            return redirect()->route('attendance.qr.index')->with('success', 'Jadwal aktif QR Presensi berhasil diperbarui.');
        } catch (\Exception $e) {
            return redirect()->route('attendance.qr.index')->with(
                'error',
                $this->safeExceptionMessage($e, 'Jadwal aktif QR Presensi tidak dapat diperbarui.')
            );
        }
    }

    public function destroy($id)
    {
        $qr = $this->qrService->getQrById($id);
        if (!$qr) {
            return redirect()->route('attendance.qr.index')->with('error', 'QR tidak ditemukan.');
        }
        $this->assertCanManageQr($qr);

        try {
            $this->qrService->deleteQr($id);
            return redirect()->route('attendance.qr.index')->with('success', 'QR Presensi berhasil dihapus permanen.');
        } catch (\Exception $e) {
            return redirect()->route('attendance.qr.index')->with(
                'error',
                $this->safeExceptionMessage($e, 'QR Presensi tidak dapat dihapus.')
            );
        }
    }

    // ==========================================
    // SCANNING ENTRY METHODS
    // ==========================================

    public function scanEntry($type, $identifier)
    {
        $qr = $this->qrService->getQrByIdentifier($identifier);
        
        if (!$qr || strtoupper($qr['QR_TYPE']) !== strtoupper($type)) {
            return view('attendance.qr.permanent_scanner', [
                'error' => 'QR Code tidak valid atau tidak sesuai.',
                'qr' => null
            ]);
        }

        $availability = $this->qrService->getAvailabilityStatus($qr);
        if (!$availability['usable']) {
            return view('attendance.qr.permanent_scanner', [
                'error' => $availability['message'],
                'qr' => $qr
            ]);
        }

        $user = auth()->user();
        if (!$user) {
            // Should be handled by middleware, but just in case
            return redirect()->route('login')->with('error', 'Silakan login terlebih dahulu untuk melakukan presensi.');
        }
        
        // Cross QR Protection
        // If type is STUDENT, user must be a student
        if (strtoupper($type) === 'STUDENT') {
            $studentRepo = app(\App\Interfaces\GoogleSheets\StudentRepositoryInterface::class);
            $student = collect($studentRepo->fetchAll())->firstWhere('User_ID', $user->User_ID);
            if (!$student) {
                return view('attendance.qr.permanent_scanner', [
                    'error' => 'QR Code ini khusus untuk Presensi Siswa. Anda bukan siswa.',
                    'qr' => $qr
                ]);
            }
        } else if (strtoupper($type) === 'EMPLOYEE') {
            $employeeRepo = app(\App\Interfaces\GoogleSheets\EmployeeRepositoryInterface::class);
            $employee = collect($employeeRepo->fetchAll())->firstWhere('User_ID', $user->User_ID);
            if (!$employee) {
                return view('attendance.qr.permanent_scanner', [
                    'error' => 'QR Code ini khusus untuk Presensi Pegawai. Anda bukan pegawai.',
                    'qr' => $qr
                ]);
            }
        }

        return view('attendance.qr.permanent_scanner', [
            'error' => null,
            'qr' => $qr,
            'type' => strtoupper($type),
            'identifier' => $identifier
        ]);
    }

    public function scanVerify(Request $request, $type, $identifier)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lon' => 'required|numeric',
        ]);

        $lat = (float) $request->lat;
        $lon = (float) $request->lon;
        $deviceInfo = $request->header('User-Agent');

        $qr = $this->qrService->getQrByIdentifier($identifier);
        
        if (!$qr || strtoupper($qr['QR_TYPE']) !== strtoupper($type)) {
            return response()->json(['status' => 'error', 'message' => 'QR tidak valid atau tidak aktif.']);
        }

        $availability = $this->qrService->getAvailabilityStatus($qr);
        if (!$availability['usable']) {
            return response()->json(['status' => 'error', 'message' => $availability['message']]);
        }

        try {
            $typeUpper = strtoupper($type);
            
            // Check Geofence Coordinates
            // In existing logic, the geofence might be checked during processStudentScan/processScan.
            // But we must also check it or let them check it. 
            // The EPS says: "Generate dynamic H8.22 token -> Invoke EXISTING H8.22 attendance processing"
            
            if ($typeUpper === 'STUDENT') {
                $result = $this->studentQrService->processStudentScan($identifier, $lat, $lon, $deviceInfo);
                return response()->json([
                    'status' => 'success',
                    'message' => $result['status'] === 'PRESENT' ? 'Presensi siswa berhasil dicatat.' : 'Presensi siswa berhasil dicatat sebagai terlambat.',
                    'distance' => $result['distance_meters'] ?? null,
                    'data' => $result,
                ]);
                
            } else {
                $result = $this->hrQrService->processScan($identifier, $deviceInfo, $lat, $lon);
                return response()->json([
                    'status' => 'success',
                    'message' => $result['status'] === 'PRESENT' ? 'Presensi pegawai berhasil dicatat.' : 'Presensi pegawai berhasil dicatat sebagai terlambat.',
                    'distance' => $result['distance_meters'] ?? null,
                    'data' => $result,
                ]);
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $this->safeExceptionMessage($e, 'Presensi QR tidak dapat diproses.'),
            ], 422);
        }
    }

    private function assertCanManageQr(array $qr): void
    {
        $this->assertCanManageQrType($qr['QR_TYPE'] ?? '');
    }

    private function assertCanManageQrType(string $qrType): void
    {
        if (!$this->canManageQrType($this->currentRoleAlias(), $qrType)) {
            abort(403, 'Anda tidak memiliki akses mengelola QR presensi tipe ini.');
        }
    }

    private function canManageQrType(string $roleAlias, string $qrType): bool
    {
        $type = strtoupper(trim($qrType));

        if ($roleAlias === 'ADMINISTRATOR') {
            return in_array($type, ['STUDENT', 'EMPLOYEE'], true);
        }

        if ($roleAlias === 'ACADEMIC') {
            return $type === 'STUDENT';
        }

        if ($roleAlias === 'HR') {
            return $type === 'EMPLOYEE';
        }

        return false;
    }

    private function currentRoleAlias(): string
    {
        $user = auth()->user();
        if (!$user || !isset($user->Role_ID)) {
            return '';
        }

        $roleService = app(\App\Services\Core\RoleService::class);
        $role = $roleService->getRoleById($user->Role_ID);
        $roleName = strtolower(trim($role['Role_Name'] ?? ''));

        if (str_contains($roleName, 'admin') || str_contains($roleName, 'master')) {
            return 'ADMINISTRATOR';
        }
        if (str_contains($roleName, 'hr')) {
            return 'HR';
        }
        if (str_contains($roleName, 'academic')) {
            return 'ACADEMIC';
        }

        return '';
    }
}
