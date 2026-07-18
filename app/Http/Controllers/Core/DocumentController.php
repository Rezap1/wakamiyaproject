<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Services\Core\DocumentService;
use App\Services\Core\ActivityLogService;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Interfaces\GoogleSheets\ApplicationRepositoryInterface;
use App\Interfaces\GoogleSheets\StudentRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DocumentController extends Controller
{
    protected DocumentService $documentService;
    protected ActivityLogService $activityLogService;
    protected ApplicationRepositoryInterface $applicationRepository;
    protected StudentRepositoryInterface $studentRepository;

    public function __construct(
        DocumentService $documentService, 
        ActivityLogService $activityLogService,
        ApplicationRepositoryInterface $applicationRepository,
        StudentRepositoryInterface $studentRepository
    ) {
        $this->documentService = $documentService;
        $this->activityLogService = $activityLogService;
        $this->applicationRepository = $applicationRepository;
        $this->studentRepository = $studentRepository;
    }

    public function index()
    {
        try {
            $documents = $this->documentService->getAllDocuments();
            $documents = collect($documents)->where('Is_Active', '!=', 'FALSE')->values()->all();

            $applications = collect($this->applicationRepository->fetchAll())->where('Is_Active', '!=', 'FALSE')->values()->all();
            $students = collect($this->studentRepository->fetchAll())->where('Is_Active', '!=', 'FALSE')->values()->all();

            return view('documents.index', compact('documents', 'applications', 'students'));
        } catch (\Exception $e) {
            Log::error('Error fetching documents: ' . $e->getMessage());
            return redirect()->route('dashboard')->with('error', 'Gagal memuat data Document: ' . $e->getMessage());
        }
    }

    public function create()
    {
        try {
            $applications = collect($this->applicationRepository->fetchAll())
                ->where('Is_Active', '!=', 'FALSE')
                ->values()
                ->all();
                
            $students = collect($this->studentRepository->fetchAll())
                ->where('Is_Active', '!=', 'FALSE')
                ->values()
                ->all();

            return view('documents.create', compact('applications', 'students'));
        } catch (\Exception $e) {
            Log::error('Error loading create document form: ' . $e->getMessage());
            return redirect()->route('documents.index')->with('error', 'Terjadi kesalahan sistem.');
        }
    }

    public function store(StoreDocumentRequest $request)
    {
        try {
            $data = $request->validated();
            $currentUser = auth()->user()->Email ?? 'system';

            // Auto verification check based on status
            if ($data['Document_Status'] === 'VERIFIED') {
                $data['Verified_By'] = $currentUser;
                $data['Verification_Date'] = now()->toDateString();
            }

            $this->documentService->createDocument($data, $currentUser);

            $this->activityLogService->log(
                'DOCUMENT',
                'CREATE',
                "Menambahkan data dokumen baru.",
                [],
                $data,
                request()->ip(),
                request()->userAgent()
            );

            return redirect()->route('documents.index')->with('success', 'Data Dokumen berhasil ditambahkan.');
        } catch (\Exception $e) {
            Log::error('Error creating document: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Gagal menyimpan data Dokumen: ' . $e->getMessage());
        }
    }

    public function show(string $id)
    {
        try {
            $document = $this->documentService->getDocumentById($id);

            if (!$document || ($document['Is_Active'] ?? 'TRUE') === 'FALSE') {
                return redirect()->route('documents.index')->with('error', 'Data Dokumen tidak ditemukan.');
            }

            $this->activityLogService->log(
                'DOCUMENT',
                'VIEW',
                "Melihat detail dokumen: {$id}",
                [],
                [],
                request()->ip(),
                request()->userAgent()
            );

            return view('documents.show', compact('document'));
        } catch (\Exception $e) {
            Log::error('Error viewing document: ' . $e->getMessage());
            return redirect()->route('documents.index')->with('error', 'Terjadi kesalahan sistem.');
        }
    }

    public function edit(string $id)
    {
        try {
            $document = $this->documentService->getDocumentById($id);

            if (!$document || ($document['Is_Active'] ?? 'TRUE') === 'FALSE') {
                return redirect()->route('documents.index')->with('error', 'Data Dokumen tidak ditemukan.');
            }

            $applications = collect($this->applicationRepository->fetchAll())
                ->where('Is_Active', '!=', 'FALSE')
                ->values()
                ->all();
                
            $students = collect($this->studentRepository->fetchAll())
                ->where('Is_Active', '!=', 'FALSE')
                ->values()
                ->all();

            return view('documents.edit', compact('document', 'applications', 'students'));
        } catch (\Exception $e) {
            Log::error('Error loading edit document form: ' . $e->getMessage());
            return redirect()->route('documents.index')->with('error', 'Terjadi kesalahan sistem.');
        }
    }

    public function update(UpdateDocumentRequest $request, string $id)
    {
        try {
            $oldData = $this->documentService->getDocumentById($id);
            if (!$oldData) {
                return redirect()->route('documents.index')->with('error', 'Data Dokumen tidak ditemukan.');
            }

            $data = $request->validated();
            $currentUser = auth()->user()->Email ?? 'system';

            // Verification logic update
            if ($data['Document_Status'] === 'VERIFIED' && ($oldData['Document_Status'] ?? '') !== 'VERIFIED') {
                $data['Verified_By'] = $currentUser;
                $data['Verification_Date'] = now()->toDateString();
            }

            $this->documentService->updateDocument($id, $data, $currentUser);

            $this->activityLogService->log(
                'DOCUMENT',
                'UPDATE',
                "Memperbarui data dokumen: {$id}",
                $oldData,
                $data,
                request()->ip(),
                request()->userAgent()
            );

            return redirect()->route('documents.index')->with('success', 'Data Dokumen berhasil diperbarui.');
        } catch (\Exception $e) {
            Log::error('Error updating document: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Gagal memperbarui data: ' . $e->getMessage());
        }
    }

    public function destroy(string $id)
    {
        try {
            $document = $this->documentService->getDocumentById($id);
            if (!$document) {
                return redirect()->route('documents.index')->with('error', 'Data Dokumen tidak ditemukan.');
            }

            $currentUser = auth()->user()->Email ?? 'system';
            $this->documentService->deleteDocument($id, $currentUser);

            $this->activityLogService->log(
                'DOCUMENT',
                'DELETE',
                "Menghapus data dokumen: {$id}",
                $document,
                ['Is_Active' => 'FALSE'],
                request()->ip(),
                request()->userAgent()
            );

            return redirect()->route('documents.index')->with('success', 'Data Dokumen berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error('Error deleting document: ' . $e->getMessage());
            return redirect()->route('documents.index')->with('error', 'Gagal menghapus data: ' . $e->getMessage());
        }
    }
}
