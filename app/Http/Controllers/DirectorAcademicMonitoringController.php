<?php

namespace App\Http\Controllers;

use App\Services\Academic\AcademicMonitoringService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class DirectorAcademicMonitoringController extends Controller
{
    public function __construct(private readonly AcademicMonitoringService $monitoring) {}

    public function index(Request $request): Response
    {
        return $this->page($request, ['type' => 'index']);
    }

    public function batch(Request $request, string $batch): Response
    {
        return $this->page($request, ['type' => 'batch', 'batch_id' => $batch]);
    }

    public function classDetail(Request $request, string $batch, string $class): Response
    {
        return $this->page($request, ['type' => 'class', 'batch_id' => $batch, 'class_id' => $class]);
    }

    public function student(Request $request, string $batch, string $class, string $student): Response
    {
        return $this->page($request, ['type' => 'student', 'batch_id' => $batch, 'class_id' => $class, 'student_id' => $student]);
    }

    public function indexData(Request $request): JsonResponse
    {
        return $this->data($request, ['type' => 'index']);
    }

    public function batchData(Request $request, string $batch): JsonResponse
    {
        return $this->data($request, ['type' => 'batch', 'batch_id' => $batch]);
    }

    public function classData(Request $request, string $batch, string $class): JsonResponse
    {
        return $this->data($request, ['type' => 'class', 'batch_id' => $batch, 'class_id' => $class]);
    }

    public function studentData(Request $request, string $batch, string $class, string $student): JsonResponse
    {
        return $this->data($request, ['type' => 'student', 'batch_id' => $batch, 'class_id' => $class, 'student_id' => $student]);
    }

    public function indexPdf(Request $request)
    {
        return $this->pdf($request, ['type' => 'index']);
    }

    public function batchPdf(Request $request, string $batch)
    {
        return $this->pdf($request, ['type' => 'batch', 'batch_id' => $batch]);
    }

    public function classPdf(Request $request, string $batch, string $class)
    {
        return $this->pdf($request, ['type' => 'class', 'batch_id' => $batch, 'class_id' => $class]);
    }

    public function studentPdf(Request $request, string $batch, string $class, string $student)
    {
        return $this->pdf($request, ['type' => 'student', 'batch_id' => $batch, 'class_id' => $class, 'student_id' => $student]);
    }

    private function page(Request $request, array $scope): Response
    {
        $monitoring = $this->withPagePaths($this->monitoring->build($scope, $this->filters($request)), $scope);

        return response()->view('director.academic-monitoring.index', compact('monitoring'))
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    private function data(Request $request, array $scope): JsonResponse
    {
        $monitoring = $this->withPagePaths($this->monitoring->build($scope, $this->filters($request)), $scope);

        return response()->json([
            'updated_at' => $monitoring['updated_at'],
            'updated_label' => $monitoring['updated_label'],
            'trend' => $monitoring['trend'],
            'chapter_trend' => $monitoring['chapter_trend'],
            'distribution' => $monitoring['distribution'],
            'html' => view('director.academic-monitoring._content', compact('monitoring'))->render(),
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    private function pdf(Request $request, array $scope)
    {
        $monitoring = $this->monitoring->build($scope, $this->filters($request));
        $filename = 'monitoring-akademik-'.$scope['type'].'-'.now('Asia/Jakarta')->format('Ymd-His').'.pdf';
        $pdf = Pdf::loadView('pdf.director_academic_monitoring', compact('monitoring'))->setPaper('a4', 'landscape');
        $pdf->getDomPDF()->getOptions()->set([
            'defaultFont' => 'DejaVu Sans',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => false,
        ]);

        return $pdf->download($filename, ['Cache-Control' => 'private, no-store, max-age=0']);
    }

    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'period' => ['nullable', Rule::in(['daily', 'weekly', 'monthly', 'all'])],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'category' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]*$/'],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['name', 'number', 'status'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $timezone = config('app.timezone', 'Asia/Jakarta');
        $end = CarbonImmutable::parse($validated['end_date'] ?? now($timezone)->toDateString(), $timezone)->startOfDay();
        $start = CarbonImmutable::parse($validated['start_date'] ?? $end->subWeeks(11)->startOfWeek()->toDateString(), $timezone)->startOfDay();
        if ($start->diffInDays($end) > 366) {
            throw ValidationException::withMessages(['end_date' => 'Rentang monitoring maksimal 366 hari. Gunakan Keseluruhan untuk histori lengkap.']);
        }
        $days = $start->diffInDays($end) + 1;

        return [
            'period' => $validated['period'] ?? 'weekly',
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'comparison_from' => $start->subDays($days)->toDateString(),
            'category' => strtoupper(trim((string) ($validated['category'] ?? ''))),
            'search' => trim((string) ($validated['search'] ?? '')),
            'sort' => $validated['sort'] ?? 'name',
            'direction' => $validated['direction'] ?? 'asc',
            'per_page' => (int) ($validated['per_page'] ?? 20),
            'page' => (int) ($validated['page'] ?? 1),
        ];
    }

    private function withPagePaths(array $monitoring, array $scope): array
    {
        $path = match ($scope['type']) {
            'batch' => route('director.academic-monitoring.batch', [$scope['batch_id']]),
            'class' => route('director.academic-monitoring.class', [$scope['batch_id'], $scope['class_id']]),
            'student' => route('director.academic-monitoring.student', [$scope['batch_id'], $scope['class_id'], $scope['student_id']]),
            default => route('director.academic-monitoring.index'),
        };

        foreach (['student_paginator', 'student_history'] as $key) {
            if (($monitoring[$key] ?? null) instanceof LengthAwarePaginator) {
                $monitoring[$key]->setPath($path);
            }
        }

        return $monitoring;
    }
}
