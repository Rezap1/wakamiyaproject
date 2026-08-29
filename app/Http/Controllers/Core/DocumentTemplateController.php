<?php
namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Document\TemplateService;

class DocumentTemplateController extends Controller
{
    protected $tplService;

    public function __construct(TemplateService $tplService)
    {
        $this->tplService = $tplService;
    }

    public function index()
    {
        $templates = $this->tplService->getAll();
        return view('document.templates.index', compact('templates'));
    }

    public function create()
    {
        return view('document.templates.create');
    }

    public function store(\App\Http\Requests\StoreDocumentTemplateRequest $request)
    {
        try {
            $data = $request->validated();
            $this->tplService->create($data);
            return redirect()->route('templates.index')->with('success', 'Template created successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)])->withInput();
        }
    }

    public function edit($id)
    {
        $template = $this->tplService->getById($id);
        if (!$template) {
            abort(404);
        }

        return view('document.templates.edit', compact('template'));
    }

    public function update(\App\Http\Requests\UpdateDocumentTemplateRequest $request, $id)
    {
        try {
            $data = $request->validated();
            $this->tplService->update($id, $data);
            return redirect()->route('templates.index')->with('success', 'Template updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)])->withInput();
        }
    }
}
