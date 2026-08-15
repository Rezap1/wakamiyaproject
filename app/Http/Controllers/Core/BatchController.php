<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBatchRequest;
use App\Http\Requests\UpdateBatchRequest;
use App\Services\Core\BatchService;
use App\Services\Core\ProgramService;
use App\Services\Core\ActivityLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class BatchController extends Controller
{
    use \App\Traits\Exportable;

    protected $exportDateField = 'Created_At';

        protected function getExportConfig(\Illuminate\Http\Request $request)
    {

        $batches = $this->batchService->getAllBatches();
        $programs = $this->programService->getAllPrograms();

        $batches = $batches->map(function ($batch) use ($programs) {
            $program = $programs->firstWhere('Program_ID', $batch['Program_ID']);
            $batch['Program_Name'] = $program ? $program['Program_Name'] : 'Tidak Ditemukan';
            return $batch;
        });

        $search = $request->input('search');
        if (!empty($search)) {
            $batches = \App\Helpers\CollectionHelper::search($batches, $search, ['Batch_ID', 'Batch_Code', 'Batch_Name', 'Program_Name', 'Description']);
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status !== 'all') {
                $batches = $batches->where('Is_Active', $status === 'active' ? 'TRUE' : 'FALSE');
            }
        }
        
        if ($request->filled('program_id')) {
            $programId = $request->input('program_id');
            if ($programId !== 'all') {
                $batches = $batches->where('Program_ID', $programId);
            }
        }
        
        return [
            'moduleName' => 'Angkatan (Batch)',
            'data' => collect(array_values($batches->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['Kode Angkatan', 'Nama Angkatan', 'Program', 'Status'],
            'mapRow' => function($row) {

                return [
                    $row['Batch_Code'] ?? '-',
                    $row['Batch_Name'] ?? '-',
                    $row['Program_Name'] ?? '-',
                    ($row['Is_Active'] ?? '') === 'TRUE' ? 'Aktif' : 'Tidak Aktif'
                ];
                    },
            'isLandscape' => true,
            'summary' => '<tr><td>Total Data</td><td>: '.$batches->count().'</td></tr>'
        ];
    }

    protected $batchService;
    protected $programService;

    public function __construct(
        BatchService $batchService,
        ProgramService $programService
    ) {
        $this->batchService = $batchService;
        $this->programService = $programService;
    }

    public function index(\Illuminate\Http\Request $request)
    {
        try {
            $batches = $this->batchService->getAllBatches();
            $programs = $this->programService->getAllPrograms();

            // Mapping Program Name to Batches for display
            $batches = $batches->map(function ($batch) use ($programs) {
                $program = $programs->firstWhere('Program_ID', $batch['Program_ID']);
                $batch['Program_Name'] = $program ? $program['Program_Name'] : 'Program Tidak Ditemukan';
                $batch['Program_Code'] = $program ? $program['Program_Code'] : '-';
                return $batch;
            });

            $search = $request->input('search');
            if (!empty($search)) {
                $batches = \App\Helpers\CollectionHelper::search($batches, $search, ['Batch_ID', 'Batch_Code', 'Batch_Name', 'Program_Name', 'Description']);
            }

            if ($request->filled('status')) {
                $status = $request->input('status');
                if ($status !== 'all') {
                    $batches = $batches->where('Is_Active', $status === 'active' ? 'TRUE' : 'FALSE');
                }
            }
            
            if ($request->filled('program_id')) {
                $programId = $request->input('program_id');
                if ($programId !== 'all') {
                    $batches = $batches->where('Program_ID', $programId);
                }
            }

            // Pagination
            $batchesPaginated = \App\Helpers\CollectionHelper::paginate($batches, 10)->withQueryString();
            
            // For filter
            $activePrograms = $programs->where('Is_Active', 'TRUE')->values();

            return view('batches.index', [
                'batches' => $batchesPaginated,
                'programs' => $activePrograms
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching batches: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Gagal memuat data master batch dari Google Sheets.');
        }
    }

    public function create()
    {
        try {
            $programs = $this->programService->getAllPrograms()->where('Is_Active', 'TRUE')->values();
            return view('batches.create', compact('programs'));
        } catch (\Exception $e) {
            Log::error('Error loading create batch form: ' . $e->getMessage());
            return redirect()->route('batches.index')->with('error', 'Gagal memuat data program untuk pendaftaran angkatan.');
        }
    }

    public function store(StoreBatchRequest $request)
    {
        try {
            $data = $request->validated();
            $batch = $this->batchService->createBatch($data);

            return redirect()->route('batches.index')->with('success', 'Angkatan (Batch) berhasil ditambahkan.');
        } catch (\Exception $e) {
            Log::error('Error creating batch: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat menyimpan data: ' . $e->getMessage())->withInput();
        }
    }

    public function show($id)
    {
        try {
            $batch = $this->batchService->getBatchById($id);
            if (!$batch) {
                return redirect()->route('batches.index')->with('error', 'Data angkatan tidak ditemukan.');
            }

            $programs = $this->programService->getAllPrograms();
            $program = $programs->firstWhere('Program_ID', $batch['Program_ID']);
            $batch['Program_Name'] = $program ? $program['Program_Name'] : 'Program Tidak Ditemukan';
            $batch['Program_Code'] = $program ? $program['Program_Code'] : '-';

            return view('batches.show', compact('batch'));
        } catch (\Exception $e) {
            Log::error('Error showing batch: ' . $e->getMessage());
            return redirect()->route('batches.index')->with('error', 'Terjadi kesalahan saat memuat data angkatan.');
        }
    }

    public function edit($id)
    {
        try {
            $batch = $this->batchService->getBatchById($id);
            if (!$batch) {
                return redirect()->route('batches.index')->with('error', 'Data angkatan tidak ditemukan.');
            }

            $programs = $this->programService->getAllPrograms()->where('Is_Active', 'TRUE')->values();
            
            // Pastikan program yang sudah tidak aktif tapi dipakai oleh batch ini tetap bisa tampil jika diperlukan
            $currentProgram = $this->programService->getProgramById($batch['Program_ID']);
            if ($currentProgram && ($currentProgram['Is_Active'] ?? 'TRUE') === 'FALSE') {
                $programs->push($currentProgram);
            }

            return view('batches.edit', compact('batch', 'programs'));
        } catch (\Exception $e) {
            Log::error('Error editing batch: ' . $e->getMessage());
            return redirect()->route('batches.index')->with('error', 'Terjadi kesalahan saat memuat form edit angkatan.');
        }
    }

    public function update(UpdateBatchRequest $request, $id)
    {
        try {
            $batch = $this->batchService->getBatchById($id);
            if (!$batch) {
                return redirect()->route('batches.index')->with('error', 'Data angkatan tidak ditemukan.');
            }

            $data = $request->validated();
            $this->batchService->updateBatch($id, $data);

            return redirect()->route('batches.index')->with('success', 'Data angkatan berhasil diperbarui.');
        } catch (\Exception $e) {
            Log::error('Error updating batch: ' . $e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat memperbarui data: ' . $e->getMessage())->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $batch = $this->batchService->getBatchById($id);
            if (!$batch) {
                return redirect()->route('batches.index')->with('error', 'Data angkatan tidak ditemukan.');
            }

            $this->batchService->deleteBatch($id);

            return redirect()->route('batches.index')->with('success', 'Data angkatan berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error('Error deleting batch: ' . $e->getMessage());
            return redirect()->route('batches.index')->with('error', 'Terjadi kesalahan saat menghapus data angkatan.');
        }
    }
}
