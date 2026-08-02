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
