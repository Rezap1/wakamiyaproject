<?php

namespace App\Http\Controllers\Academic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Academic\SubjectService;
use App\Services\Core\ActivityLogService;

class SubjectController extends Controller
{
    use \App\Traits\Exportable;

    protected $exportDateField = 'Created_At';

        protected function getExportConfig(\Illuminate\Http\Request $request)
    {

        $subjects = $this->subjectService->getAll();
        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $subjects = $subjects->filter(function($item) use ($search) {
                return str_contains(strtolower($item['Subject_Code'] ?? ''), $search) || 
                       str_contains(strtolower($item['Subject_Name'] ?? ''), $search);
            })->values();
        }
        
        return [
            'moduleName' => 'Materi',
            'data' => collect(array_values($subjects->toArray())),
            'pdfView' => 'pdf.generic_table',
            'headers' => ['ID Mapel', 'Kode', 'Nama Materi', 'Deskripsi'],
            'mapRow' => function($row) {

                return [
                    $row['Subject_ID'] ?? '-',
                    $row['Subject_Code'] ?? '-',
                    $row['Subject_Name'] ?? '-',
                    $row['Description'] ?? '-'
                ];
                    },
            'isLandscape' => true,
            'summary' => '<tr><td>Total Data</td><td>: '.$subjects->count().'</td></tr>'
        ];
    }

    protected $subjectService;
    protected $programService;

    public function __construct(SubjectService $subjectService, \App\Services\Core\ProgramService $programService)
    {
        $this->subjectService = $subjectService;
        $this->programService = $programService;
    }

    public function index(Request $request)
    {
        $subjects = $this->subjectService->getAll();
        
        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $subjects = $subjects->filter(function($item) use ($search) {
                return str_contains(strtolower($item['Subject_Code'] ?? ''), $search) || 
                       str_contains(strtolower($item['Subject_Name'] ?? ''), $search);
            })->values();
        }
        
        if ($request->filled('status') && $request->status !== 'ALL') {
            $subjects = $subjects->where('Is_Active', $request->status);
        }

        return view('academic.subjects.index', compact('subjects'));
    }

    public function create()
    {
        $programs = $this->programService->getAllPrograms()->where('Is_Active', 'TRUE')->values();
        return view('academic.subjects.create', compact('programs'));
    }

    public function store(\App\Http\Requests\StoreSubjectRequest $request)
    {
        try {
            $data = $request->except('_token');
            $this->subjectService->create($data);
            return redirect()->route('subjects.index')->with('success', 'Subject created successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)])->withInput();
        }
    }

    public function show($id)
    {
        return $this->edit($id);
    }

    public function edit($id)
    {
        $subject = $this->subjectService->getById($id);
        if (!$subject) return redirect()->route('subjects.index')->withErrors(['error' => 'Not found']);
        
        $programs = $this->programService->getAllPrograms()->where('Is_Active', 'TRUE')->values();
        return view('academic.subjects.edit', compact('subject', 'programs'));
    }

    public function update(\App\Http\Requests\UpdateSubjectRequest $request, $id)
    {
        try {
            $data = $request->except(['_token', '_method']);
            $this->subjectService->update($id, $data);
            return redirect()->route('subjects.index')->with('success', 'Subject updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)])->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $this->subjectService->delete($id);
            return redirect()->route('subjects.index')->with('success', 'Subject deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)]);
        }
    }
}
