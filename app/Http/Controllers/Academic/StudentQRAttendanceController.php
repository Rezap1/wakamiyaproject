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

        $userId = trim((string) ($user->User_ID ?? ''));
        $student = collect($this->studentRepository->fetchAll())
            ->first(fn ($candidate) => $userId !== ''
                && strcasecmp(trim((string) ($candidate['User_ID'] ?? '')), $userId) === 0);

        return view('academic.attendances.qr_scanner', compact('user', 'student'));
    }

    /**
     * Process Student Geo-Fenced QR Scan
     */
    public function scan(\Illuminate\Http\Request $request)
    {
        try {
            $validated = $request->validate([
                'token' => 'required|string',
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'device_info' => 'nullable|string|max:255'
            ], [
                'token.required' => 'QR Code token wajib diisi.',
                'latitude.required' => 'Koordinat latitude lokasi perangkat wajib diberikan.',
                'latitude.between' => 'Koordinat latitude tidak valid.',
                'longitude.required' => 'Koordinat longitude lokasi perangkat wajib diberikan.',
                'longitude.between' => 'Koordinat longitude tidak valid.'
            ]);
            $token = $validated['token'];
            $latitude = (float) $validated['latitude'];
            $longitude = (float) $validated['longitude'];
            $deviceInfo = $validated['device_info'] ?? null;

            $result = $this->studentQrService->processStudentScan($token, $latitude, $longitude, $deviceInfo);

            $isCheckOut = ($result['action'] ?? 'CHECK_IN') === 'CHECK_OUT';
            $statusLabel = $result['status'] === 'PRESENT' ? 'Tepat Waktu' : "Terlambat {$result['late_minutes']} Menit";

            return response()->json([
                'success' => true,
                'message' => $isCheckOut
                    ? 'Check-Out Berhasil Dicatat!'
                    : "Check-In Berhasil Dicatat! ({$statusLabel})",
                'data' => $result
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Never write the scanned token to application logs.
            \Illuminate\Support\Facades\Log::warning('Scan QR validation failed', [
                'errors' => $e->errors(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal: ' . json_encode($e->errors())
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $this->safeExceptionMessage($e, 'Presensi QR siswa tidak dapat diproses.')
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
                'message' => $this->safeExceptionMessage($e, 'Token QR siswa tidak dapat dibuat.')
            ], 400);
        }
    }
}
