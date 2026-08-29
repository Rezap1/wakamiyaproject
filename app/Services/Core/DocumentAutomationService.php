<?php

namespace App\Services\Core;

use App\Interfaces\GoogleSheets\DocumentRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Support\ActorIdentity;
use Exception;

class DocumentAutomationService
{
    protected $documentRepo;
    protected $enterpriseEvent;

    public function __construct(DocumentRepositoryInterface $documentRepo, EnterpriseEventService $enterpriseEvent)
    {
        $this->documentRepo = $documentRepo;
        $this->enterpriseEvent = $enterpriseEvent;
    }

    /**
     * Generate Document PDF
     *
     * @param string $documentType (Invoice, Receipt, Payroll, Certificate, AcademicReport)
     * @param string $referenceType
     * @param string $referenceId
     * @param array $data Data to be passed to Blade view
     * @param string $viewName Blade view path (e.g. 'pdf.invoice')
     * @return array|bool Result or false on failure
     */
    public function generateDocument($documentType, $referenceType, $referenceId, $data, $viewName, $user = 'System')
    {
        try {
            $user = ActorIdentity::resolve((string) $user);

            // 1. Determine ID Prefix and Folder
            $prefix = $this->getPrefix($documentType);
            $folder = $this->getFolder($documentType);

            // 2. Handle Versioning
            $existingDocs = collect($this->documentRepo->getAll())
                ->where('Reference_Type', $referenceType)
                ->where('Reference_ID', $referenceId)
                ->where('Document_Type', $documentType);

            $version = 1;
            foreach ($existingDocs as $doc) {
                $version = max($version, (int) str_replace('v', '', $doc['Version'] ?? 'v1') + 1);
            }

            // 3. Generate New ID
            $documentId = $this->documentRepo->generateNewId($prefix . '-' . date('y'), 5);

            // 4. Set File Path
            $filename = "{$documentId}.pdf";
            $relativePath = "documents/{$folder}/{$filename}";

            // 5. Inject meta data into view
            $data['document_meta'] = [
                'document_id' => $documentId,
                'generated_at' => now()->format('Y-m-d H:i:s'),
                'version' => "v{$version}",
                'title' => $this->getTitle($documentType)
            ];

            // 6. Generate PDF
            $pdf = Pdf::loadView($viewName, $data);
            $pdfContent = $pdf->output();
            
            // 7. Calculate Hash
            $hash = hash('sha256', $pdfContent);

            // 8. Save to Storage
            if (!Storage::disk('local')->put($relativePath, $pdfContent)) {
                throw new Exception('Gagal menyimpan dokumen ke storage private.');
            }

            // 9. Record to MASTER_DOCUMENT
            $docData = [
                'Document_ID' => $documentId,
                'Document_Type' => $documentType,
                'Reference_Type' => $referenceType,
                'Reference_ID' => $referenceId,
                'Generated_At' => now()->toDateTimeString(),
                'Generated_By' => $user,
                'Version' => "v{$version}",
                'File_Path' => $relativePath,
                'Status' => 'Active',
                'Hash' => $hash,
            ];

            $created = $this->documentRepo->create($docData);
            if (!$created) {
                Storage::disk('local')->delete($relativePath);
                throw new Exception('Gagal menyimpan metadata dokumen.');
            }

            foreach ($existingDocs as $doc) {
                if (($doc['Status'] ?? '') === 'Active') {
                    $archived = $this->documentRepo->update($doc['Document_ID'], ['Status' => 'Archived']);
                    if (!$archived) {
                        throw new Exception("Gagal mengarsipkan versi dokumen {$doc['Document_ID']}.");
                    }
                }
            }
            $this->documentRepo->clearCache();

            return $docData;

        } catch (Exception $e) {
            // Error Handling: Do not break main transaction
            Log::error("Document Automation Failed [{$documentType} / {$referenceId}]: " . $e->getMessage());
            
            try {
                // Notify Administrator
                $this->enterpriseEvent->dispatch(
                    'DOCUMENT',
                    'GENERATE_FAILED',
                    strtoupper($documentType),
                    $referenceId,
                    Auth::check() ? ActorIdentity::required() : 'SYSTEM',
                    ['ADMINISTRATOR'],
                    [],
                    ['error' => $e->getMessage()]
                );
            } catch (Exception $ne) {}
            
            return false;
        }
    }

    private function getPrefix($documentType)
    {
        return match($documentType) {
            'Invoice' => 'INV',
            'Receipt' => 'RCT',
            'Payroll' => 'PAY',
            'Certificate' => 'CRT',
            'AcademicReport' => 'RPT',
            default => 'DOC'
        };
    }

    private function getFolder($documentType)
    {
        return match($documentType) {
            'Invoice' => 'invoice',
            'Receipt' => 'receipt',
            'Payroll' => 'payroll',
            'Certificate' => 'certificate',
            'AcademicReport' => 'academic-report',
            default => 'other'
        };
    }

    private function getTitle($documentType)
    {
        return match($documentType) {
            'Invoice' => 'INVOICE / TAGIHAN',
            'Receipt' => 'PAYMENT RECEIPT / BUKTI PEMBAYARAN',
            'Payroll' => 'PAYROLL SLIP / SLIP GAJI',
            'Certificate' => 'CERTIFICATE OF COMPLETION / SERTIFIKAT',
            'AcademicReport' => 'ACADEMIC REPORT / TRANSKRIP NILAI',
            default => 'DOCUMENT'
        };
    }

}
