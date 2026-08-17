<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\ScanStudentQRRequest;
use App\Services\Academic\StudentQRAttendanceService;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;

class StudentQRAttendanceController extends Controller
{
    protected $studentQrService;
    protected $studentRepository;

    public function __construct(
        StudentQRAttendanceService $studentQrService,
        StudentRepositoryInterface $studentRepository
    ) {
        $this->studentQrService = $studentQrService;
        $this->studentRepository = $studentRepository;
    }

    /**
     * Display Student QR Scanner Page
     */
    public function scanner()
    {
        $user = auth()->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $allStudents = collect($this->studentRepository->fetchAll());
        $student = $allStudents->firstWhere('User_ID', $user->User_ID);

        return view('academic.attendances.qr_scanner', compact('user', 'student'));
    }

    /**
     * Process Student Geo-Fenced QR Scan
     */
    public function scan(ScanStudentQRRequest $request)
    {
        try {
            $validated = $request->validated();
            $token = $validated['token'];
            $latitude = (float) $validated['latitude'];
            $longitude = (float) $validated['longitude'];
            $deviceInfo = $validated['device_info'] ?? null;

            $result = $this->studentQrService->processStudentScan($token, $latitude, $longitude, $deviceInfo);

            $statusLabel = $result['status'] === 'PRESENT' ? 'Tepat Waktu' : "Terlambat {$result['late_minutes']} Menit";

            return response()->json([
                'success' => true,
                'message' => "Absensi Berhasil Dicatat! ({$statusLabel})",
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * API to get current dynamic student QR token (for display on screen)
     */
    public function getDynamicToken()
    {
        try {
            $data = $this->studentQrService->generateStudentDynamicToken();
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
}
