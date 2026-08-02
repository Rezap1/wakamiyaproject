<?php
namespace App\Services\Document;

use App\Interfaces\GoogleSheets\DocumentRepositoryInterface;
use App\Interfaces\GoogleSheets\DocumentTemplateRepositoryInterface;
use App\Services\Core\EnterpriseEventService;
use Illuminate\Support\Facades\Auth;

class DocumentService
{
    protected $docRepo, $tplRepo, $enterpriseEvent;

    public function __construct(
        DocumentRepositoryInterface $docRepo,
        DocumentTemplateRepositoryInterface $tplRepo,
        EnterpriseEventService $enterpriseEvent
    ) {
        $this->docRepo = $docRepo;
        $this->tplRepo = $tplRepo;
        $this->enterpriseEvent = $enterpriseEvent;
    }

    public function getAll() { return $this->docRepo->getAll(); }
    public function getById($id) { return $this->docRepo->getById($id); }

    public function GenerateDocumentNumber($docType)
    {
        $types = config('document.types', []);
        $prefix = $types[$docType] ?? 'DOC';
        $year = date('Y');
        $count = $this->docRepo->getAll()->count() + 1;
        return sprintf("%s-%s-%06d", $prefix, $year, $count);
    }

    public function GenerateQRCodePlaceholder($id)
    {
        // Generates a URL or string for the QR code
        return "QR-WMS-" . md5($id);
    }

    public function GenerateSignaturePlaceholder($user)
    {
        return "DSIG-" . strtoupper($user);
    }

    public function GenerateDocumentMetadata(array $payload)
    {
        $payload['Document_ID'] = uniqid('DOC_');
        
        if (empty($payload['Document_Number'])) {
            $payload['Document_Number'] = $this->GenerateDocumentNumber($payload['Document_Type'] ?? 'Custom Document');
        } else {
            $existing = $this->docRepo->getAll()->firstWhere('Document_Number', $payload['Document_Number']);
            if ($existing) {
                throw new \Exception("Document Number {$payload['Document_Number']} sudah terdaftar.");
            }
        }
        
        $payload['Generated_Date'] = now()->toDateTimeString();
        $payload['Status'] = 'Generated';
        $payload['QRCode'] = $this->GenerateQRCodePlaceholder($payload['Document_ID']);
        $payload['Digital_Signature'] = $this->GenerateSignaturePlaceholder($payload['Generated_By'] ?? 'SYSTEM');
        $payload['Created_At'] = now()->toDateTimeString();
        return $payload;
    }

    public function GenerateDocument(array $data)
    {
        $metadata = $this->GenerateDocumentMetadata($data);
        $res = $this->docRepo->create($metadata);
        $this->docRepo->clearCache();
        try { 
            $this->enterpriseEvent->dispatch(
                'DOCUMENT',
                'GENERATE',
                'DOCUMENT',
                $metadata['Document_ID'] ?? 'UNKNOWN',
                Auth::id() ?? 0,
                ['ADMINISTRATOR'],
                [],
                []
            );
        } catch(\Exception $e) {}
        return $res;
    }

    public function GenerateDocumentStub($module, $refId, $docType)
    {
        // Hook for other modules to auto-generate document
        return [
            'Reference_Module' => $module,
            'Reference_ID' => $refId,
            'Document_Type' => $docType,
            'Status' => 'Draft'
        ];
    }

    public function PreviewDocument($id)
    {
        $doc = $this->getById($id);
        if(!$doc) return null;
        
        $template = null;
        if(isset($doc['Template_ID'])) {
            $template = $this->tplRepo->getById($doc['Template_ID']);
        }
        
        return [
            'document' => $doc,
            'template' => $template,
            'html' => $template ? "<h1>Preview of {$template['Template_Name']}</h1><p>Doc Number: {$doc['Document_Number']}</p>" : "<h1>No Template Assigned</h1>"
        ];
    }

    public function DuplicateDocument($id, $user)
    {
        $doc = $this->getById($id);
        if(!$doc) throw new \Exception("Document not found");

        unset($doc['Document_ID']);
        $doc['Generated_By'] = $user;
        $doc['Title'] = 'Copy of ' . ($doc['Title'] ?? 'Document');
        
        return $this->GenerateDocument($doc);
    }

    public function ArchiveDocument($id)
    {
        $res = $this->docRepo->update($id, ['Status' => 'Archived', 'Updated_At' => now()->toDateTimeString()]);
        $this->docRepo->clearCache();
        try { 
            $this->enterpriseEvent->dispatch(
                'DOCUMENT',
                'ARCHIVE',
                'DOCUMENT',
                $id,
                Auth::id() ?? 0,
                ['ADMINISTRATOR'],
                [],
                []
            );
        } catch(\Exception $e) {}
        return $res;
    }

    public function PublishDocument($id)
    {
        $res = $this->docRepo->update($id, ['Status' => 'Published', 'Updated_At' => now()->toDateTimeString()]);
        $this->docRepo->clearCache();
        try { 
            $this->enterpriseEvent->dispatch(
                'DOCUMENT',
                'PUBLISH',
                'DOCUMENT',
                $id,
                Auth::id() ?? 0,
                ['ADMINISTRATOR'],
                [],
                []
            );
        } catch(\Exception $e) {}
        return $res;
    }
}