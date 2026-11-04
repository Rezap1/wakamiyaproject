<?php

namespace App\Traits;

use Illuminate\Http\Request;
use App\Helpers\ReportHelper;

trait Exportable
{
    /**
     * Define this in the controller to return export configuration.
     * @return array [
     *    'moduleName' => 'USERS',
     *    'data' => Collection,
     *    'pdfView' => 'users.pdf',
     *    'headers' => ['Col1', 'Col2'],
     *    'mapRow' => function($row) { ... },
     *    'isLandscape' => true,
     *    'metadata' => [],
     *    'summary' => 'html string'
     * ]
     */
    abstract protected function getExportConfig(Request $request);

    public function previewPdf(Request $request) { return $this->handleExport($request, 'preview'); }
    public function exportPdf(Request $request) { return $this->handleExport($request, 'pdf'); }
    public function exportExcel(Request $request) { return $this->handleExport($request, 'excel'); }
    public function exportCsv(Request $request) { return $this->handleExport($request, 'csv'); }
    public function print(Request $request) { return $this->handleExport($request, 'print'); }

    private function handleExport(Request $request, $format)
    {
        $request->validate([
            'export_filter' => ['nullable', 'in:all,today,range,current_page'],
            'start_date' => ['nullable', 'required_if:export_filter,range', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'required_if:export_filter,range', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'sort_order' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'orientation' => ['nullable', 'in:auto,portrait,landscape'],
        ]);
        $config = $this->getExportConfig($request);
        $data = $config['data'] ?? collect([]);
        $sourceCount = $data->count();
        $dateField = $config['dateField'] ?? (property_exists($this, 'exportDateField') ? $this->exportDateField : 'Created_At');
        
        // 1. Reject invalid rows instead of silently losing source records.
        foreach ($data as $row) {
            if (!is_array($row) || empty($row)) {
                throw new \UnexpectedValueException('Dataset laporan berisi record tidak valid. Periksa sumber data.');
            }
        }

        // 2. Export Filter (all, today, range, current_page)
        $filter = $request->input('export_filter', 'all');
        
        if ($filter === 'today' && !($config['dateFilterApplied'] ?? false)) {
            $today = \Carbon\Carbon::today()->format('Y-m-d');
            $data = $data->filter(function($row) use ($dateField, $today) {
                if (!data_get($row, $dateField)) return false;
                try {
                    return \Carbon\Carbon::parse(data_get($row, $dateField))->timezone(config('app.timezone'))->format('Y-m-d') === $today;
                } catch (\Exception $e) { return false; }
            });
        } elseif ($filter === 'range' && !($config['dateFilterApplied'] ?? false)) {
            $start = $request->input('start_date');
            $end = $request->input('end_date');
            if ($start && $end) {
                $data = $data->filter(function($row) use ($dateField, $start, $end) {
                    if (!data_get($row, $dateField)) return false;
                    try {
                        $d = \Carbon\Carbon::parse(data_get($row, $dateField))->timezone(config('app.timezone'))->format('Y-m-d');
                        return $d >= $start && $d <= $end;
                    } catch (\Exception $e) { return false; }
                });
            }
        }

        // 3. Sorting
        $sortOrder = $request->input('sort_order', $request->input('sort', 'desc'));
        if ($filter === 'current_page') {
            // The controller supplies the same ordered collection as its index.
            $page = max(1, (int) $request->input('page', 1));
            $perPage = $config['perPage'] ?? 10;
            if ($config['paginate'] ?? true) {
                $data = $data->slice(($page - 1) * $perPage, $perPage);
            }
        } elseif ($sortOrder === 'desc') {
            $data = $data->sortByDesc(function($row) use ($dateField) {
                if (!data_get($row, $dateField)) return 0;
                try {
                    return \Carbon\Carbon::parse(data_get($row, $dateField))->timestamp;
                } catch (\Exception $e) { return 0; }
            });
        } else {
            $data = $data->sortBy(function($row) use ($dateField) {
                if (!data_get($row, $dateField)) return 0;
                try {
                    return \Carbon\Carbon::parse(data_get($row, $dateField))->timestamp;
                } catch (\Exception $e) { return 0; }
            });
        }

        $data = $data->values();

        // An empty report still records the requested scope and zero records.

        // 5. Orientation Override
        $orientation = $request->input('orientation', 'auto');
        if ($orientation === 'portrait') {
            $config['isLandscape'] = false;
        } elseif ($orientation === 'landscape') {
            $config['isLandscape'] = true;
        }

        $config['data'] = $data;
        $metadata = $config['metadata'] ?? [];
        $metadata['summary'] = isset($config['summaryForData'])
            ? $config['summaryForData']($data)
            : ($sourceCount === $data->count()
                ? ($config['summary'] ?? $metadata['summary'] ?? null)
                : '<tr><td>Total Data Sesuai Filter</td><td>: '.$data->count().'</td></tr>');
        $metadata['filterLabel'] = ($metadata['filterLabel'] ?? '').' | Ekspor: '.$filter
            .($filter === 'range' ? ' '.$request->input('start_date').' s.d. '.$request->input('end_date') : '')
            .($filter === 'today' ? ' '.now()->toDateString() : '')
            .($filter === 'current_page' ? ' '.$request->input('page', 1) : '');

        return ReportHelper::export(
            $format,
            $config['moduleName'],
            $config['data'],
            $metadata,
            $config['pdfView'],
            $config['headers'] ?? [],
            $config['mapRow'] ?? null,
            $config['isLandscape'] ?? false
        );
    }
}
