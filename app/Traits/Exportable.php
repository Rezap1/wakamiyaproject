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
        $config = $this->getExportConfig($request);
        $data = $config['data'] ?? collect([]);
        $dateField = property_exists($this, 'exportDateField') ? $this->exportDateField : 'Created_At';
        
        // 1. Data Validation: Remove null rows and empty objects
        $data = $data->reject(function($row) {
            return empty($row) || !is_array($row);
        });

        // Remove duplicate records safely by JSON encoding
        $data = $data->unique(function($item) {
            return json_encode($item);
        });

        // 2. Export Filter (all, today, range, current_page)
        $filter = $request->input('export_filter', 'all');
        
        if ($filter === 'today') {
            $today = \Carbon\Carbon::today()->format('Y-m-d');
            $data = $data->filter(function($row) use ($dateField, $today) {
                if (!isset($row[$dateField]) || empty($row[$dateField])) return false;
                try {
                    return \Carbon\Carbon::parse($row[$dateField])->format('Y-m-d') === $today;
                } catch (\Exception $e) { return false; }
            });
        } elseif ($filter === 'range') {
            $start = $request->input('start_date');
            $end = $request->input('end_date');
            if ($start && $end) {
                $data = $data->filter(function($row) use ($dateField, $start, $end) {
                    if (!isset($row[$dateField]) || empty($row[$dateField])) return false;
                    try {
                        $d = \Carbon\Carbon::parse($row[$dateField])->format('Y-m-d');
                        return $d >= $start && $d <= $end;
                    } catch (\Exception $e) { return false; }
                });
            }
        } elseif ($filter === 'current_page') {
            $page = max(1, (int) $request->input('page', 1));
            $perPage = 10;
            $data = $data->slice(($page - 1) * $perPage, $perPage);
        }

        // 3. Sorting
        $sortOrder = $request->input('sort_order', 'desc');
        if ($sortOrder === 'desc') {
            $data = $data->sortByDesc(function($row) use ($dateField) {
                if (!isset($row[$dateField]) || empty($row[$dateField])) return 0;
                try {
                    return \Carbon\Carbon::parse($row[$dateField])->timestamp;
                } catch (\Exception $e) { return 0; }
            });
        } else {
            $data = $data->sortBy(function($row) use ($dateField) {
                if (!isset($row[$dateField]) || empty($row[$dateField])) return 0;
                try {
                    return \Carbon\Carbon::parse($row[$dateField])->timestamp;
                } catch (\Exception $e) { return 0; }
            });
        }

        $data = $data->values();

        // 4. Empty State Handling
        if ($data->isEmpty()) {
            return back()->with('warning', 'There is no data matching the selected criteria.');
        }

        // 5. Orientation Override
        $orientation = $request->input('orientation', 'auto');
        if ($orientation === 'portrait') {
            $config['isLandscape'] = false;
        } elseif ($orientation === 'landscape') {
            $config['isLandscape'] = true;
        }

        $config['data'] = $data;

        if (isset($config['summary'])) {
            view()->share('executive_summary', $config['summary']);
        }

        return ReportHelper::export(
            $format,
            $config['moduleName'],
            $config['data'],
            $config['metadata'] ?? [],
            $config['pdfView'],
            $config['headers'] ?? [],
            $config['mapRow'] ?? null,
            $config['isLandscape'] ?? false
        );
    }
}
