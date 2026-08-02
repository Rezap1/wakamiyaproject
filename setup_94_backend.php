<?php

$dirConfig = 'config';
$dirInterface = 'app/Interfaces/GoogleSheets';
$dirRepo = 'app/Repositories/GoogleSheets';
$dirService = 'app/Services/Document';

if(!is_dir($dirService)) mkdir($dirService, 0755, true);

// 1. Config
$docConfig = <<<'EOT'
<?php

return [
    'types' => [
        'Employee Salary Slip' => 'SLIP',
        'Payroll Report' => 'DOC',
        'Student Invoice' => 'INV',
        'Payment Receipt' => 'RCT',
        'Registration Form' => 'DOC',
        'Student Agreement' => 'DOC',
        'Employment Agreement' => 'DOC',
        'Interview Result' => 'DOC',
        'Placement Letter' => 'LTR',
        'Acceptance Letter' => 'LTR',
        'COE Document' => 'DOC',
        'Visa Document' => 'DOC',
        'Medical Report' => 'DOC',
        'Training Certificate' => 'CERT',
        'Graduation Certificate' => 'CERT',
        'Warning Letter' => 'LTR',
        'Recommendation Letter' => 'LTR',
        'Announcement Letter' => 'LTR',
        'Custom Document' => 'DOC'
    ],
    'status' => [
        'Draft',
        'Generated',
        'Waiting Approval',
        'Approved',
        'Published',
        'Archived',
        'Cancelled'
    ]
];
EOT;
file_put_contents("$dirConfig/document.php", $docConfig);

// 2. Interfaces
$docInterface = <<<'EOT'
<?php
namespace App\Interfaces\GoogleSheets;
interface DocumentRepositoryInterface {
    public function getAll();
    public function getById($id);
    public function create(array $data);
    public function update($id, array $data);
    public function delete($id);
}
EOT;
file_put_contents("$dirInterface/DocumentRepositoryInterface.php", $docInterface);

$tplInterface = <<<'EOT'
<?php
namespace App\Interfaces\GoogleSheets;
interface DocumentTemplateRepositoryInterface {
    public function getAll();
    public function getById($id);
    public function create(array $data);
    public function update($id, array $data);
    public function delete($id);
}
EOT;
file_put_contents("$dirInterface/DocumentTemplateRepositoryInterface.php", $tplInterface);

// 3. Repositories
$docRepo = <<<'EOT'
<?php
namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\DocumentRepositoryInterface;

class DocumentRepository extends BaseSheetRepository implements DocumentRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_DOCUMENT';
        $this->cacheKey = 'document_sheet';
        $this->primaryKey = 'Document_ID';
    }

    public function getAll() { return $this->fetchAll(); }
    public function getById($id) { return $this->fetchAll()->firstWhere($this->primaryKey, $id); }
    public function create(array $data) { return $this->append($data); }
    public function update($id, array $data) { return $this->updateRow($id, $data); }
    public function delete($id) { return $this->updateRow($id, ['Status' => 'Cancelled']); }
}
EOT;
file_put_contents("$dirRepo/DocumentRepository.php", $docRepo);

$tplRepo = <<<'EOT'
<?php
namespace App\Repositories\GoogleSheets;

use App\Interfaces\GoogleSheets\DocumentTemplateRepositoryInterface;

class DocumentTemplateRepository extends BaseSheetRepository implements DocumentTemplateRepositoryInterface
{
    public function __construct()
    {
        parent::__construct();
        $this->sheetName = 'MASTER_DOCUMENT_TEMPLATE';
        $this->cacheKey = 'document_template_sheet';
        $this->primaryKey = 'Template_ID';
    }

    public function getAll() { return $this->fetchAll(); }
    public function getById($id) { return $this->fetchAll()->firstWhere($this->primaryKey, $id); }
    public function create(array $data) { return $this->append($data); }
    public function update($id, array $data) { return $this->updateRow($id, $data); }
    public function delete($id) { return $this->updateRow($id, ['Status' => 'Inactive']); }
}
EOT;
file_put_contents("$dirRepo/DocumentTemplateRepository.php", $tplRepo);

// 4. Services
$tplService = <<<'EOT'
<?php
namespace App\Services\Document;

use App\Interfaces\GoogleSheets\DocumentTemplateRepositoryInterface;

class TemplateService
{
    protected $repo;

    public function __construct(DocumentTemplateRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    public function getAll() { return $this->repo->getAll(); }
    public function getById($id) { return $this->repo->getById($id); }
    public function create(array $data) {
        $data['Template_ID'] = uniqid('TPL_');
        $data['Created_At'] = now()->toDateTimeString();
        $data['Status'] = 'Active';
        $res = $this->repo->create($data);
        $this->repo->clearCache();
        return $res;
    }
    public function update($id, array $data) {
        $data['Updated_At'] = now()->toDateTimeString();
        $res = $this->repo->update($id, $data);
        $this->repo->clearCache();
        return $res;
    }
}
EOT;
file_put_contents("$dirService/TemplateService.php", $tplService);

$docService = <<<'EOT'
<?php
namespace App\Services\Document;

use App\Interfaces\GoogleSheets\DocumentRepositoryInterface;
use App\Interfaces\GoogleSheets\DocumentTemplateRepositoryInterface;

class DocumentService
{
    protected $docRepo, $tplRepo;

    public function __construct(
        DocumentRepositoryInterface $docRepo,
        DocumentTemplateRepositoryInterface $tplRepo
    ) {
        $this->docRepo = $docRepo;
        $this->tplRepo = $tplRepo;
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
        // Generates a mock URL or string for the QR placeholder
        return "QR-WMS-" . md5($id);
    }

    public function GenerateSignaturePlaceholder($user)
    {
        return "DSIG-" . strtoupper($user);
    }

    public function GenerateDocumentMetadata(array $payload)
    {
        $payload['Document_ID'] = uniqid('DOC_');
        $payload['Document_Number'] = $this->GenerateDocumentNumber($payload['Document_Type'] ?? 'Custom Document');
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
        return $res;
    }

    public function PublishDocument($id)
    {
        $res = $this->docRepo->update($id, ['Status' => 'Published', 'Updated_At' => now()->toDateTimeString()]);
        $this->docRepo->clearCache();
        return $res;
    }
}
EOT;
file_put_contents("$dirService/DocumentService.php", $docService);

echo "Backend Foundation created successfully.\n";
?>
