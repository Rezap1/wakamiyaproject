<?php
namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Document\DocumentService;

class DocumentController extends Controller
{
    use \App\Traits\Exportable;

    protected $exportDateField = 'Generated_At';

        protected function getExportConfig(\Illuminate\Http\Request $request)
    {

        $documents = $this->docService->getAll();
        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $documents = $documents->filter(function($item) use ($search) {
                return str_contains(strtolower($item['Document_Name'] ?? ''), $search);
            })->values();
        }
        
        return [
            'moduleName' => 'Dokumen (Document)',
            'data' => collect(array_values($documents->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['ID Dokumen', 'Nama Dokumen', 'Kategori', 'Tipe', 'Status'],
            'mapRow' => function($row) {

                return [
                    $row['Document_ID'] ?? '-',
                    $row['Document_Name'] ?? '-',
                    $row['Category'] ?? '-',
                    $row['Type'] ?? '-',
                    $row['Status'] ?? '-'
                ];
                    },
            'isLandscape' => true,
            'summary' => '<tr><td>Total Data</td><td>: '.$documents->count().'</td></tr>'
        ];
    }

    protected $docService;

    public function __construct(DocumentService $docService)
    {
        $this->docService = $docService;
    }

    public function index()
    {
        $documents = $this->docService->getAll();
        return view('documents.index', compact('documents'));
    }

    public function create(\App\Repositories\GoogleSheets\StudentRepository $studentRepo)
    {
        $students = $studentRepo->fetchAll();
        return view('documents.create', compact('students'));
    }

    public function store(\App\Http\Requests\StoreDocumentRequest $request)
    {
        try {
            $data = $request->except('_token');
            $data['Generated_By'] = $this->authenticatedActor();
            $this->docService->GenerateDocument($data);
            return redirect()->route('documents.index')->with('success', 'Document generated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)])->withInput();
        }
    }

    public function show($id)
    {
        $document = $this->docService->getById($id);
        return view('documents.show', compact('document'));
    }

    public function edit($id, \App\Repositories\GoogleSheets\StudentRepository $studentRepo)
    {
        $document = $this->docService->getById($id);
        $students = $studentRepo->fetchAll();
        return view('documents.edit', compact('document', 'students'));
    }

    public function update(\App\Http\Requests\UpdateDocumentRequest $request, $id)
    {
        // For documents, update is restricted, usually we archive or publish
        return redirect()->route('documents.index');
    }

    public function destroy($id)
    {
        $this->docService->ArchiveDocument($id);
        return redirect()->route('documents.index')->with('success', 'Document archived.');
    }

    private function authenticatedActor(): string
    {
        $user = auth()->user();
        $actor = $user->User_ID ?? $user->Email ?? $user->email ?? null;
        if (!$actor) {
            abort(403, 'Identitas pengguna tidak valid.');
        }

        return (string) $actor;
    }
}
