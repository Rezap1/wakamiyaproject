<?php

namespace App\Http\Controllers\Core;

use App\Helpers\CollectionHelper;
use App\Http\Controllers\Controller;
use App\Services\Dashboard\DirectorDashboardService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DirectorDashboardController extends Controller
{
    private const REPORT_DIVISIONS = ['finance', 'academic', 'hr', 'marketing', 'approvals'];

    public function __construct(private readonly DirectorDashboardService $dashboardService) {}

    public function index(Request $request)
    {
        $filters = $this->validatedFilters($request, false);

        try {
            $dashboardData = $this->dashboardService->getDashboardData($filters);
        } catch (\Throwable $e) {
            report($e);
            $dashboardData = [
                'api_error' => true,
                'error_message' => $this->safeExceptionMessage($e),
                'filters' => $filters,
            ];
        }

        return view('dashboard.director', $dashboardData);
    }

    public function reports(Request $request)
    {
        $filters = $this->validatedFilters($request, true);

        try {
            $report = $this->dashboardService->report($filters['division'], $filters);
            $report['paginated'] = CollectionHelper::paginate($report['rows'], 25)->withQueryString();
            unset($report['rows']);
            $report['filters'] = $filters;
        } catch (\Throwable $e) {
            report($e);
            $report = [
                'api_error' => true,
                'error_message' => $this->safeExceptionMessage($e),
                'filters' => $filters,
            ];
        }

        return view('director.reports.index', $report);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $filters = $this->validatedFilters($request, true);
        $report = $this->dashboardService->report($filters['division'], $filters);
        $filename = 'laporan-direktur-'.$filters['division'].'-'.$filters['start_date'].'-'.$filters['end_date'].'.csv';

        return response()->streamDownload(function () use ($report): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, array_values($report['columns']));
            foreach ($report['rows'] as $row) {
                fputcsv($output, array_map(
                    fn ($field) => $this->safeCsvCell($row[$field] ?? ''),
                    array_keys($report['columns'])
                ));
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function validatedFilters(Request $request, bool $withReportFilters): array
    {
        $timezone = config('app.timezone', 'Asia/Jakarta');
        $today = now($timezone);
        $defaults = [
            'start_date' => $today->copy()->startOfMonth()->toDateString(),
            'end_date' => $today->toDateString(),
        ];

        $rules = [
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ];
        if ($withReportFilters) {
            $rules += [
                'division' => ['nullable', Rule::in(self::REPORT_DIVISIONS)],
                'search' => ['nullable', 'string', 'max:100'],
                'status' => ['nullable', 'string', 'max:50'],
                'program_id' => ['nullable', 'string', 'max:80'],
                'batch_id' => ['nullable', 'string', 'max:80'],
                'department_id' => ['nullable', 'string', 'max:80'],
            ];
        }

        $validated = $request->validate($rules);
        $filters = [
            'start_date' => $validated['start_date'] ?? $defaults['start_date'],
            'end_date' => $validated['end_date'] ?? $defaults['end_date'],
        ];

        if (Carbon::parse($filters['start_date'], $timezone)->diffInDays(Carbon::parse($filters['end_date'], $timezone)) > 366) {
            throw ValidationException::withMessages(['end_date' => 'Rentang laporan maksimal 366 hari.']);
        }

        if ($withReportFilters) {
            $filters += [
                'division' => $validated['division'] ?? 'finance',
                'search' => trim((string) ($validated['search'] ?? '')),
                'status' => trim((string) ($validated['status'] ?? '')),
                'program_id' => trim((string) ($validated['program_id'] ?? '')),
                'batch_id' => trim((string) ($validated['batch_id'] ?? '')),
                'department_id' => trim((string) ($validated['department_id'] ?? '')),
            ];
        }

        return $filters;
    }

    private function safeCsvCell(mixed $value): string
    {
        $value = is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);

        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }
}
