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

        $logs = $this->auditService->activity();
        if ($request->input('export_filter') === 'current_page') {
            $logs = $logs->take(100);
        }
        $users = collect(app(\App\Interfaces\GoogleSheets\UserRepositoryInterface::class)->fetchAll())->keyBy('User_ID');
        
        return [
            'moduleName' => 'Audit Log',
            'paginate' => false,
            'data' => collect(array_values($logs->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['Waktu', 'Aksi', 'Modul', 'Pengguna', 'IP Address'],
            'mapRow' => function($row) use ($users) {

                return [
                    isset($row['Created_At']) ? \Carbon\Carbon::parse($row['Created_At'])->format('d M Y H:i:s') : '-',
                    $row['Action'] ?? '-',
                    $row['Module'] ?? '-',
                    $row['User_Name'] ?? \App\Support\Reporting\HumanReadableResolver::userName($row['User_ID'] ?? '', $users),
                    $row['IP_Address'] ?? $row['IPAddress'] ?? '-'
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
