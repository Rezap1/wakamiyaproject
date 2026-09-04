<?php
namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Core\AuditLogService;

class AuditLogController extends Controller
{
    use \App\Traits\Exportable;

    protected $exportDateField = 'Created_At';

        protected function getExportConfig(\Illuminate\Http\Request $request)
    {

        $logs = $this->auditService->activity()->take(100);
        
        return [
            'moduleName' => 'Audit Log',
            'data' => collect(array_values($logs->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['Waktu', 'Aksi', 'Modul', 'Pengguna', 'IP Address'],
            'mapRow' => function($row) {

                return [
                    isset($row['Created_At']) ? \Carbon\Carbon::parse($row['Created_At'])->format('d M Y H:i:s') : '-',
                    $row['Action'] ?? '-',
                    $row['Module'] ?? '-',
                    $row['User_Name'] ?? '-',
                    $row['IP_Address'] ?? '-'
                ];
                    },
            'isLandscape' => true,
            'summary' => '<tr><td>Total Data</td><td>: '.$logs->count().'</td></tr>'
        ];
    }

    protected $auditService;

    public function __construct(AuditLogService $auditService)
    {
        $this->auditService = $auditService;
    }

    public function index(Request $request)
    {
        $logs = $this->auditService->activity()->take(100); // Take 100 for performance
        return view('audit.index', compact('logs'));
    }

    public function show($id)
    {
        $log = $this->auditService->getById($id);
        if(!$log) abort(404);
        return view('audit.show', compact('log'));
    }

    public function statistics()
    {
        $stats = $this->auditService->statistics();
        return view('audit.statistics', compact('stats'));
    }
}
