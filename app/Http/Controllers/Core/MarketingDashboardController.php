<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

use App\Interfaces\GoogleSheets\CompanyRepositoryInterface;
use App\Interfaces\GoogleSheets\DocumentRepositoryInterface;
use App\Interfaces\GoogleSheets\ActivityLogRepositoryInterface;

class MarketingDashboardController extends Controller
{
    protected $companyRepo, $documentRepo, $activityLogRepo;

    public function __construct(
        CompanyRepositoryInterface $companyRepo,
        DocumentRepositoryInterface $documentRepo,
        ActivityLogRepositoryInterface $activityLogRepo
    ) {
        $this->companyRepo = $companyRepo;
        $this->documentRepo = $documentRepo;
        $this->activityLogRepo = $activityLogRepo;
    }

    public function index()
    {
        try {
            $dashboardData = Cache::remember('wms_marketing_dashboard_data', 300, function () {
                $countActive = function($repo) {
                    return collect($repo->fetchAll())->where('Is_Active', '!=', 'FALSE')->count();
                };

                // 1. KPI Counts
                $kpi = [
                    'companies' => $countActive($this->companyRepo),
                    'documents' => $countActive($this->documentRepo),
                ];

                // 2. Notifications & Alerts
                $incompleteDocuments = collect($this->documentRepo->fetchAll())
                    ->where('Is_Active', '!=', 'FALSE')
                    ->filter(function($d) {
                        return ($d['Document_Status'] ?? '') === 'PENDING';
                    })->values()->toArray();

                try {
                    $recentActivities = collect($this->activityLogRepo->fetchAll())
                        ->filter(function ($log) {
                            return in_array(strtoupper($log['Module'] ?? ''), ['MARKETING', 'COMPANY', 'DOCUMENT']);
                        })
                        ->sortByDesc('Created_At')
                        ->take(10)
                        ->map(function ($log) {
                            $desc = $log['Description'] ?? ($log['New_Value'] ?? '');
                            if (is_string($desc) && str_starts_with($desc, '{')) {
                                $decoded = json_decode($desc, true);
                                if (is_array($decoded) && isset($decoded['description'])) {
                                    $desc = $decoded['description'];
                                } elseif (is_array($decoded) && isset($decoded['title'])) {
                                    $desc = $decoded['title'];
                                } else {
                                    $action = str_replace('_', ' ', $log['Action'] ?? '');
                                    $refId = $log['Reference_ID'] ?? '';
                                    $desc = "Aktivitas " . ucwords(strtolower($action)) . ($refId ? " pada {$refId}" : '');
                                }
                            }
                            return [
                                'title'       => $log['Action'] ?? 'Aktivitas',
                                'description' => ($log['Module'] ?? '') . ' — ' . $desc,
                                'time'        => isset($log['Created_At']) ? Carbon::parse($log['Created_At'])->diffForHumans() : 'Baru saja',
                            ];
                        })
                        ->values()
                        ->toArray();
                } catch (\Exception $e) {
                    $recentActivities = [];
                    \Illuminate\Support\Facades\Log::error('Failed to fetch audit log for Marketing dashboard: ' . $e->getMessage());
                }

                return [
                    'kpi' => $kpi,
                    'notifications' => [
                        'incompleteDocuments' => $incompleteDocuments
                    ],
                    'recentActivities' => $recentActivities
                ];
            });
        } catch (\Exception $e) {
            $dashboardData = ['api_error' => true, 'error_message' => $this->safeExceptionMessage($e)];
        }

        return view('dashboard.marketing', $dashboardData);
    }
}
