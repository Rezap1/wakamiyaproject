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

    public function fetchAll()
    {
        return parent::fetchAll()->map(function ($row) {
            $metadata = json_decode((string) ($row['Content'] ?? ''), true);
            if (is_array($metadata) && ($metadata['format'] ?? '') === 'wms.document.v1') {
                // Canonical lifecycle/identity fields always win over archived metadata.
                $row = array_merge($metadata['document'] ?? [], $row);
            }
            return $row;
        });
    }
    public function getAll() { return $this->fetchAll(); }
    public function getById($id) { return $this->fetchAll()->firstWhere($this->primaryKey, $id); }
    public function create(array $data)
    {
        if (isset($data['File_Path'], $data['Document_Type'])) {
            // The MySQL manifest stores extensible document metadata in Content.
            // Legacy generator keys are not physical columns and were being discarded.
            $data = array_merge($data, [
                'Document_Number' => $data['Document_Number'] ?? $data['Document_ID'],
                'Module' => $data['Module'] ?? $data['Document_Type'],
                'Title' => $data['Title'] ?? $data['Document_Type'].' '.$data['Reference_ID'],
                'Generated_Date' => isset($data['Generated_At']) ? substr($data['Generated_At'], 0, 10) : null,
                'Content' => json_encode(['format' => 'wms.document.v1', 'document' => $data], JSON_THROW_ON_ERROR),
                'Created_At' => $data['Generated_At'] ?? now()->toDateTimeString(),
            ]);
        }
        return $this->append($data);
    }
    public function update($id, array $data) { return $this->updateRow($id, $data); }
    public function delete($id) { return $this->updateRow($id, ['Status' => 'Cancelled']); }
}
