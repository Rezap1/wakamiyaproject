<?php
namespace App\Services\Document;

use App\Interfaces\GoogleSheets\DocumentRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use Illuminate\Support\Facades\Auth;

class PdfService
{
    protected $docRepo;
    protected $signatureService;
    protected $enterpriseEvent;

    public function __construct(
        DocumentRepositoryInterface $docRepo, 
        SignatureService $signatureService,
        EnterpriseEventService $enterpriseEvent
    ) {
        $this->docRepo = $docRepo;
        $this->signatureService = $signatureService;
        $this->enterpriseEvent = $enterpriseEvent;
    }

    public function GenerateVerificationCode($documentNumber)
    {
        $hash = substr(hash('sha256', $documentNumber . now()->timestamp . config('app.key')), 0, 10);
        return strtoupper("VER-{$hash}");
    }

    public function GenerateQRCode($verificationCode)
    {
        // Returns URL string that would be converted to QR Image in the View
        return url('/document/verify/' . $verificationCode);
    }

    public function GenerateWatermark()
    {
        if(config('pdf.watermark.enabled')) {
            return config('pdf.watermark.text', 'WAKAMIYA');
        }
        return null;
    }

    public function CompileTemplate($docType, array $data)
    {
        $mapping = config('pdf.template_mapping');
        $view = $mapping[$docType] ?? 'document.pdf.default';
        
        if(!view()->exists($view)) {
            $view = 'document.pdf.default';
        }

        return view($view, $data)->render();
    }

    public function GenerateDocumentFile($docId, $userEmail, $role)
    {
        $doc = $this->docRepo->getById($docId);
        if(!$doc) throw new \Exception("Document not found");

        $currentVersion = intval(str_replace('V', '', $doc['Version'] ?? '0'));
        $newVersion = 'V' . ($currentVersion + 1);

        $verificationCode = $this->GenerateVerificationCode($doc['Document_Number']);
        $qrCode = $this->GenerateQRCode($verificationCode);
        
        $doc['Version'] = $newVersion;
        $doc['Verification_Code'] = $verificationCode;
        $doc['QR_Code'] = $qrCode;
        $doc['Generated_File'] = $doc['Document_Number'] . '_' . $newVersion . '.pdf';
        $doc['Generated_At'] = now()->toDateTimeString();
        $doc['Generated_By'] = $userEmail;

        if(config('pdf.digital_signature.enabled')) {
            $doc = $this->signatureService->ApplySignature($doc, $userEmail, $role);
        }

        $this->docRepo->update($docId, $doc);
        $this->docRepo->clearCache();

        return $doc;
    }

    public function PreviewPDF($docId)
    {
        $doc = $this->docRepo->getById($docId);
        if(!$doc) return null;

        $watermark = $this->GenerateWatermark();
        $html = $this->CompileTemplate($doc['Document_Type'] ?? 'Custom Document', ['document' => $doc]);

        return [
            'document' => $doc,
            'html' => $html,
            'watermark' => $watermark
        ];
    }

    public function DownloadPDF($docId)
    {
        // Simulate PDF download by returning the raw HTML for now (PDF Engine not installed yet phase 9.6 is HTML wrapper)
        // In real world, we would pass HTML to dompdf here.
        $preview = $this->PreviewPDF($docId);
        if(!$preview) abort(404);
        
        return $preview;
    }
}