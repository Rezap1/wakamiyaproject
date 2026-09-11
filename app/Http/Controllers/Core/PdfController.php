<?php
namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Document\PdfService;
use App\Services\Document\SignatureService;
use Barryvdh\DomPDF\Facade\Pdf;

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
            $userEmail = $this->authenticatedActor();
            $role = session('role') ?? 'GUEST';
            
            $this->pdfService->GenerateDocumentFile($id, $userEmail, $role);
            return back()->with('success', 'Dokumen PDF berhasil dibuat dan ditandatangani.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $this->safeExceptionMessage($e)]);
        }
    }

    public function download($id)
    {
        $data = $this->pdfService->DownloadPDF($id);
        if(!$data) abort(404);

        $configuredName = basename((string) ($data['document']['Generated_File'] ?? ''));
        $filename = str_ends_with(strtolower($configuredName), '.pdf')
            ? $configuredName
            : 'Dokumen-' . preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $id) . '.pdf';
        $orientation = strtolower((string) config('pdf.orientation', 'portrait')) === 'landscape'
            ? 'landscape'
            : 'portrait';

        return Pdf::loadHTML($data['html'])
            ->setPaper((string) config('pdf.paper_size', 'A4'), $orientation)
            ->download($filename);
    }

    public function verify($verificationCode)
    {
        // URL for QR scanning verification
        return view('document.pdf.verify', ['code' => $verificationCode]);
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
