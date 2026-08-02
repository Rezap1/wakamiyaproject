<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePositionRequest;
use App\Http\Requests\UpdatePositionRequest;
use App\Services\Core\PositionService;
use App\Services\Core\DepartmentService;
use App\Services\Core\ActivityLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class PositionController extends Controller
{
    use \App\Traits\Exportable;

    protected $exportDateField = 'Created_At';

    protected function getExportConfig(\Illuminate\Http\Request $request)
    {
        $positions = $this->positionService->getAllPositions();
        $departments = $this->departmentService->getAllDepartments();
        
        $positions = $positions->map(function ($position) use ($departments) {
            $dept = $departments->firstWhere('Department_ID', $position['Department_ID']);
            $position['Department_Name'] = $dept ? $dept['Department_Name'] : 'Tidak Diketahui';
            return $position;
        });

        $search = $request->input('search');
        if (!empty($search)) {
            $positions = \App\Helpers\CollectionHelper::search($positions, $search, ['Position_ID', 'Position_Name', 'Department_Name']);
        }
        if ($request->filled('department')) {
            $positions = $positions->where('Department_ID', $request->input('department'));
        }
        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status !== 'all') {
                $positions = $positions->where('Is_Active', $status === 'active' ? 'TRUE' : 'FALSE');
            }
        }
        
        return [
            'moduleName' => 'POSITIONS',
            'data' => $positions,
            'pdfView' => 'pdf.generic_table',
            'headers' => ['ID', 'Nama Posisi', 'Departemen', 'Status'],
            'mapRow' => function($row) {
                return [
                    $row['Position_ID'] ?? '-', 
                    $row['Position_Name'] ?? '-', 
                    $row['Department_Name'] ?? '-',
                    ($row['Is_Active'] ?? 'TRUE') === 'TRUE' ? 'Aktif' : 'Nonaktif'
                ];
            },
            'isLandscape' => false,
            'summary' => '<tr><td>Total Posisi</td><td>: '.$positions->count().'</td></tr>'
        ];
    }
    protected $positionService;
    protected $departmentService;
    protected $activityLogService;

    public function __construct(PositionService $positionService, DepartmentService $departmentService, ActivityLogService $activityLogService)
    {
        $this->positionService = $positionService;
        $this->departmentService = $departmentService;
        $this->activityLogService = $activityLogService;
    }

    public function index()
    {
        try {
            $positions = $this->positionService->getAllPositions();
            $departments = $this->departmentService->getAllDepartments();
            
            // Map department names to positions for display
            $positions = $positions->map(function ($position) use ($departments) {
                $dept = $departments->firstWhere('Department_ID', $position['Department_ID']);
                $position['Department_Name'] = $dept ? $dept['Department_Name'] : 'Tidak Diketahui';
                return $position;
            });

            // Custom collection pagination
            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $perPage = 10;
            $currentItems = $positions->slice(($currentPage - 1) * $perPage, $perPage)->all();
            $positionsPaginated = new LengthAwarePaginator($currentItems, count($positions), $perPage, $currentPage, [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
            ]);
            
            return view('positions.index', [
                'positions' => $positionsPaginated,
                'departments' => $departments->where('Is_Active', 'TRUE')
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching positions: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Gagal memuat data posisi dari Google Sheets.');
        }
    }

    public function create()
    {
        try {
            $departments = $this->departmentService->getAllDepartments()->where('Is_Active', 'TRUE');
            return view('positions.create', compact('departments'));
        } catch (\Exception $e) {
            Log::error('Error loading create position form: ' . $e->getMessage());
            return redirect()->route('positions.index')->with('error', 'Gagal memuat data referensi departemen.');
        }
    }

    public function store(StorePositionRequest $request)
    {
        try {
            $data = $request->validated();
            $position = $this->positionService->createPosition($data);
            
            $this->activityLogService->logAction(
                Auth::id() ?? 'SYSTEM',
                'CREATE',
                'MASTER_POSITION',
                "Mendaftarkan posisi baru: {$position['Position_ID']}",
                $request->ip(),
                null,
                $position,
                $request->userAgent()
            );

            return redirect()->route('positions.index')->with('success', 'Posisi berhasil ditambahkan.');
        } catch (\Exception $e) {
            Log::error('Error creating position: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat menyimpan data ke Google Sheets.')->withInput();
        }
    }

    public function show($id)
    {
        try {
            $position = $this->positionService->getPositionById($id);
            if (!$position) {
                return redirect()->route('positions.index')->with('error', 'Posisi tidak ditemukan.');
            }
            
            $dept = $this->departmentService->getDepartmentById($position['Department_ID']);
            $position['Department_Name'] = $dept ? $dept['Department_Name'] : 'Tidak Diketahui';

            return view('positions.show', compact('position'));
        } catch (\Exception $e) {
            Log::error('Error showing position: ' . $e->getMessage());
            return redirect()->route('positions.index')->with('error', 'Terjadi kesalahan saat memuat data posisi.');
        }
    }

    public function edit($id)
    {
        try {
            $position = $this->positionService->getPositionById($id);
            if (!$position) {
                return redirect()->route('positions.index')->with('error', 'Posisi tidak ditemukan.');
            }
            
            $departments = $this->departmentService->getAllDepartments()->where('Is_Active', 'TRUE');
            
            // If the current department is inactive but assigned to this position, we should still include it in the list so the dropdown doesn't break
            if (!collect($departments)->contains('Department_ID', $position['Department_ID'])) {
                $currentDept = $this->departmentService->getDepartmentById($position['Department_ID']);
                if ($currentDept) {
                    $departments->push($currentDept);
                }
            }

            return view('positions.edit', compact('position', 'departments'));
        } catch (\Exception $e) {
            Log::error('Error editing position: ' . $e->getMessage());
            return redirect()->route('positions.index')->with('error', 'Terjadi kesalahan saat memuat data posisi.');
        }
    }

    public function update(UpdatePositionRequest $request, $id)
    {
        try {
            $position = $this->positionService->getPositionById($id);
            if (!$position) {
                return redirect()->route('positions.index')->with('error', 'Posisi tidak ditemukan.');
            }

            $data = $request->validated();
            $this->positionService->updatePosition($id, $data);
            
            $this->activityLogService->logAction(
                Auth::id() ?? 'SYSTEM',
                'UPDATE',
                'MASTER_POSITION',
                "Memperbarui posisi: {$id}",
                $request->ip(),
                $position,
                array_merge($position, $data),
                $request->userAgent()
            );

            return redirect()->route('positions.index')->with('success', 'Posisi berhasil diperbarui.');
        } catch (\Exception $e) {
            Log::error('Error updating position: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat memperbarui data di Google Sheets.')->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $position = $this->positionService->getPositionById($id);
            if (!$position) {
                return redirect()->route('positions.index')->with('error', 'Posisi tidak ditemukan.');
            }

            $this->positionService->deletePosition($id);
            
            $this->activityLogService->logAction(
                Auth::id() ?? 'SYSTEM',
                'DELETE',
                'MASTER_POSITION',
                "Menonaktifkan posisi (Soft Delete): {$id}",
                request()->ip(),
                $position,
                array_merge($position, ['Is_Active' => 'FALSE']),
                request()->userAgent()
            );

            return redirect()->route('positions.index')->with('success', 'Posisi berhasil dinonaktifkan.');
        } catch (\Exception $e) {
            Log::error('Error deleting position: ' . $e->getMessage());
            return redirect()->route('positions.index')->with('error', 'Terjadi kesalahan saat menghapus data di Google Sheets.');
        }
    }
}
