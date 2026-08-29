<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProgramRequest;
use App\Http\Requests\UpdateProgramRequest;
use App\Services\Core\ProgramService;
use App\Services\Core\ActivityLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class ProgramController extends Controller
{
    use \App\Traits\Exportable;

    protected $exportDateField = 'Created_At';

        protected function getExportConfig(\Illuminate\Http\Request $request)
    {

        $programs = $this->programService->getAllPrograms();

        $search = $request->input('search');
        if (!empty($search)) {
            $programs = \App\Helpers\CollectionHelper::search($programs, $search, ['Program_Code', 'Program_Name', 'Description']);
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status !== 'all') {
                $programs = $programs->where('Is_Active', $status === 'active' ? 'TRUE' : 'FALSE');
            }
        }
        
        return [
            'moduleName' => 'Program Pelatihan (Program)',
            'data' => collect(array_values($programs->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['Kode Program', 'Nama Program', 'Durasi', 'Status'],
            'mapRow' => function($row) {

                return [
                    $row['Program_Code'] ?? '-',
                    $row['Program_Name'] ?? '-',
                    $row['Duration'] ?? '-',
                    ($row['Is_Active'] ?? '') === 'TRUE' ? 'Aktif' : 'Tidak Aktif'
                ];
                    },
            'isLandscape' => true,
            'summary' => '<tr><td>Total Data</td><td>: '.$programs->count().'</td></tr>'
        ];
    }

    protected $programService;

    public function __construct(
        ProgramService $programService
    ) {
        $this->programService = $programService;
    }

    public function index(\Illuminate\Http\Request $request)
    {
        try {
            $programs = $this->programService->getAllPrograms();

            $search = $request->input('search');
            if (!empty($search)) {
                $programs = \App\Helpers\CollectionHelper::search($programs, $search, ['Program_ID', 'Program_Code', 'Program_Name', 'Description']);
            }

            if ($request->filled('status')) {
                $status = $request->input('status');
                if ($status !== 'all') {
                    $programs = $programs->where('Is_Active', $status === 'active' ? 'TRUE' : 'FALSE');
                }
            }

            // Pagination
            $programsPaginated = \App\Helpers\CollectionHelper::paginate($programs, 10)->withQueryString();
            
            return view('programs.index', [
                'programs' => $programsPaginated
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching programs: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Gagal memuat data master program dari Google Sheets.');
        }
    }

    public function create()
    {
        return view('programs.create');
    }

    public function store(StoreProgramRequest $request)
    {
        try {
            $data = $request->validated();
            $program = $this->programService->createProgram($data);

            return redirect()->route('programs.index')->with('success', 'Program berhasil ditambahkan.');
        } catch (\Exception $e) {
            Log::error('Error creating program: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat menyimpan data: ' . $this->safeExceptionMessage($e))->withInput();
        }
    }

    public function show($id)
    {
        try {
            $program = $this->programService->getProgramById($id);
            if (!$program) {
                return redirect()->route('programs.index')->with('error', 'Data program tidak ditemukan.');
            }

            return view('programs.show', compact('program'));
        } catch (\Exception $e) {
            Log::error('Error showing program: ' . $e->getMessage());
            return redirect()->route('programs.index')->with('error', 'Terjadi kesalahan saat memuat data program.');
        }
    }

    public function edit($id)
    {
        try {
            $program = $this->programService->getProgramById($id);
            if (!$program) {
                return redirect()->route('programs.index')->with('error', 'Data program tidak ditemukan.');
            }

            return view('programs.edit', compact('program'));
        } catch (\Exception $e) {
            Log::error('Error editing program: ' . $e->getMessage());
            return redirect()->route('programs.index')->with('error', 'Terjadi kesalahan saat memuat form edit program.');
        }
    }

    public function update(UpdateProgramRequest $request, $id)
    {
        try {
            $program = $this->programService->getProgramById($id);
            if (!$program) {
                return redirect()->route('programs.index')->with('error', 'Data program tidak ditemukan.');
            }

            $data = $request->validated();
            $this->programService->updateProgram($id, $data);

            return redirect()->route('programs.index')->with('success', 'Data program berhasil diperbarui.');
        } catch (\Exception $e) {
            Log::error('Error updating program: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat memperbarui data: ' . $this->safeExceptionMessage($e))->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $program = $this->programService->getProgramById($id);
            if (!$program) {
                return redirect()->route('programs.index')->with('error', 'Data program tidak ditemukan.');
            }

            $this->programService->deleteProgram($id);

            return redirect()->route('programs.index')->with('success', 'Data program berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error('Error deleting program: ' . $e->getMessage());
            return redirect()->route('programs.index')->with('error', 'Terjadi kesalahan saat menghapus data program.');
        }
    }
}
