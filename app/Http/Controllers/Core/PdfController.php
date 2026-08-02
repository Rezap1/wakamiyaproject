<?php
namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Document\PdfService;
use App\Services\Document\SignatureService;

class PdfController extends Controller
{
    protected $pdfService;
    protected $signatureService;

    public function __construct(PdfService $pdfService, SignatureService $signatureService)
    {
        $this->pdfService = $pdfService;
        $this->signatureService = $signatureService;
    }

    public function preview($id)
    {
        $data = $this->pdfService->PreviewPDF($id);
        if(!$data) abort(404);
        return view('document.pdf.wrapper', $data);
    }

    public function generate(Request $request, $id)
    {
        try {
            $userEmail = auth()->user()->email ?? (auth()->user()->User_ID ?? 'user@example.com');
            $role = session('role') ?? 'GUEST';
            
            $this->pdfService->GenerateDocumentFile($id, $userEmail, $role);
            return back()->with('success', 'PDF Document successfully generated and signed.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function download($id)
    {
        // Placeholder for actual PDF download response
        $data = $this->pdfService->DownloadPDF($id);
        if(!$data) abort(404);
        
        // Normally: return PDF::loadHTML($data['html'])->download($data['document']['Generated_File']);
        // For now, render HTML print view
        return view('document.pdf.wrapper', $data);
    }

    public function verify($verificationCode)
    {
        // URL for QR scanning verification
        return view('document.pdf.verify', ['code' => $verificationCode]);
    }
}
