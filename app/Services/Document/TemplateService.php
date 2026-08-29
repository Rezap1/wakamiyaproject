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
        $data['Status'] = $data['Status'] ?? 'Active';
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
