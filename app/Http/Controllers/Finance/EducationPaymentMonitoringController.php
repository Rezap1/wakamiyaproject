<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\EducationPaymentMonitoringService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EducationPaymentMonitoringController extends Controller
{
    public function __construct(
        private EducationPaymentMonitoringService $monitoringService,
    ) {}

    public function index(Request $request)
    {
        $filters = validator($request->query(), [
            'search' => ['nullable', 'string', 'max:100'],
            'class_id' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(array_keys(EducationPaymentMonitoringService::STATUS_LABELS))],
        ])->validate();

        return view(
            'finance.education-payments.index',
            $this->monitoringService->build($filters),
        );
    }

    public function show(string $studentId)
    {
        $detail = $this->monitoringService->detail($studentId);
        abort_if($detail === null, 404, 'Data monitoring pembayaran siswa tidak ditemukan.');

        return view('finance.education-payments.show', $detail);
    }
}
