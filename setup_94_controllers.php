<?php
$dirCtrl = 'app/Http/Controllers/Core';

// 1. Document Controller
$docCtrl = <<<'EOT'
<?php
namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Document\DocumentService;

class DocumentController extends Controller
{
    protected $docService;

    public function __construct(DocumentService $docService)
    {
        $this->docService = $docService;
    }

    public function index()
    {
        $documents = $this->docService->getAll();
        return view('document.index', compact('documents'));
    }

    public function create()
    {
        return view('document.create');
    }

    public function store(Request $request)
    {
        try {
            $data = $request->except('_token');
            $data['Generated_By'] = auth()->user()->email ?? 'System';
            $this->docService->GenerateDocument($data);
            return redirect()->route('documents.index')->with('success', 'Document generated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function show($id)
    {
        $document = $this->docService->getById($id);
        return view('document.show', compact('document'));
    }

    public function edit($id)
    {
        $document = $this->docService->getById($id);
        return view('document.edit', compact('document'));
    }

    public function update(Request $request, $id)
    {
        // For documents, update is restricted, usually we archive or publish
        return redirect()->route('documents.index');
    }

    public function destroy($id)
    {
        $this->docService->ArchiveDocument($id);
        return redirect()->route('documents.index')->with('success', 'Document archived.');
    }
}
EOT;
file_put_contents("$dirCtrl/DocumentController.php", $docCtrl);

// 2. Document Template Controller
$tplCtrl = <<<'EOT'
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

    public function store(Request $request)
    {
        try {
            $data = $request->except('_token');
            $this->tplService->create($data);
            return redirect()->route('templates.index')->with('success', 'Template created successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function edit($id)
    {
        $template = $this->tplService->getById($id);
        return view('document.templates.edit', compact('template'));
    }

    public function update(Request $request, $id)
    {
        try {
            $data = $request->except(['_token', '_method']);
            $this->tplService->update($id, $data);
            return redirect()->route('templates.index')->with('success', 'Template updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }
}
EOT;
file_put_contents("$dirCtrl/DocumentTemplateController.php", $tplCtrl);

// 3. Document Preview Controller
$previewCtrl = <<<'EOT'
<?php
namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Services\Document\DocumentService;

class DocumentPreviewController extends Controller
{
    protected $docService;

    public function __construct(DocumentService $docService)
    {
        $this->docService = $docService;
    }

    public function show($id)
    {
        $data = $this->docService->PreviewDocument($id);
        if(!$data) abort(404);
        
        return view('document.preview', $data);
    }
}
EOT;
file_put_contents("$dirCtrl/DocumentPreviewController.php", $previewCtrl);

echo "Controllers created.\n";
?>
